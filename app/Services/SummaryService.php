<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\Production;
use App\Models\Sale;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Schema;

/**
 * Business-intelligence calculations for the summary dashboard.
 * Keeps SummaryController thin: it reads the request and returns the view.
 */
class SummaryService
{
    private const FORECAST_DAYS = 7;
    private const ANOMALY_Z = 2;          // |z-score| above this = anomaly
    private const DEFAULT_REORDER = 10;
    private const COVER_DAYS_TARGET = 14; // suggested orders cover 14 days

    /** Everything summary.blade.php needs, for one branch or all (null). */
    public function build(?int $branchId): array
    {
        $names = Branch::orderBy('branch_id')->pluck('branch_name', 'branch_id')->all();

        $production = $this->production($branchId);
        $inventory = $this->inventory($branchId, $names);
        $comparison = $this->comparison($inventory['rows'], $names);

        $kpi = $production['kpi'];
        $kpi['revenue'] = round(
            collect($comparison)
                ->filter(fn ($c) => $branchId === null || $c['id'] === $branchId)
                ->sum('revenue'),
            2
        );

        return [
            'branch_list' => collect($names)->map(fn ($n, $id) => ['id' => (int) $id, 'name' => $n])->values()->all(),
            'kpi' => $kpi,
            'low_count' => $inventory['low_count'],
            'chart' => $production['chart'],
            'inv_chart' => $this->inventoryChart($inventory['rows'], $branchId === null),
            'inventory_rows' => $inventory['rows'],
            'comp' => $comparison,
            'anomalies' => $production['anomalies'],
            'recs' => $this->recommendations($inventory['rows'], $kpi, $production['anomalies'], $comparison),
            'has_log' => Schema::hasTable('inventory_log'),
        ];
    }

    // ---------------- Production: descriptive + predictive ----------------

    private function production(?int $branchId): array
    {
        $chart = ['labels' => [], 'qty' => [], 'ma7' => [], 'anom' => [], 'fc_labels' => [], 'fc' => []];
        $kpi = ['total' => 0, 'avg' => 0, 'change' => null, 'forecast7' => 0, 'anomalies' => 0];
        $anomalies = [];

        $rows = Production::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('production_date AS d, SUM(actual_quantity) AS qty')
            ->groupBy('production_date')
            ->orderBy('production_date')
            ->get();

        if ($rows->isEmpty()) {
            return compact('chart', 'kpi', 'anomalies');
        }

        // One value per calendar day; days with no production count as 0
        $byDate = $rows->pluck('qty', 'd')->map(fn ($v) => (float) $v)->all();
        $series = [];
        foreach (CarbonPeriod::create($rows->first()->d, $rows->last()->d) as $day) {
            $key = $day->toDateString();
            $series[$key] = $byDate[$key] ?? 0.0;
        }
        $series = array_slice($series, -60, null, true);
        $dates = array_keys($series);
        $vals = array_values($series);
        $n = count($vals);

        $mean = array_sum($vals) / $n;
        $flags = $this->anomalyFlags($vals);
        foreach ($flags as $i => $flagged) {
            if ($flagged) {
                $anomalies[] = ['date' => Carbon::parse($dates[$i])->format('M d'), 'qty' => (int) $vals[$i]];
            }
        }

        // Forecast: average of the last 7 days, repeated for the next 7
        $base = array_sum(array_slice($vals, -7)) / min(7, $n);
        $lastDay = Carbon::parse($dates[$n - 1]);
        $fcLabels = [];
        for ($i = 1; $i <= self::FORECAST_DAYS; $i++) {
            $fcLabels[] = $lastDay->copy()->addDays($i)->format('M d');
        }

        $chart = [
            'labels' => array_map(fn ($d) => Carbon::parse($d)->format('M d'), $dates),
            'qty' => array_map('intval', $vals),
            'ma7' => $this->movingAverage($vals),
            'anom' => $flags,
            'fc_labels' => $fcLabels,
            'fc' => array_fill(0, self::FORECAST_DAYS, round($base, 1)),
        ];

        // Week-over-week change
        $last7 = array_sum(array_slice($vals, -7));
        $prev7 = 0;
        if ($n > 7) {
            $from = max(0, $n - 14);
            $prev7 = array_sum(array_slice($vals, $from, ($n - 7) - $from));
        }

        $kpi = [
            'total' => (int) array_sum($vals),
            'avg' => round($mean, 1),
            'change' => $prev7 > 0 ? round(($last7 - $prev7) / $prev7 * 100, 1) : null,
            'forecast7' => (int) round($base * self::FORECAST_DAYS),
            'anomalies' => count($anomalies),
        ];

        return compact('chart', 'kpi', 'anomalies');
    }

    private function movingAverage(array $vals, int $window = 7): array
    {
        $out = [];
        foreach ($vals as $i => $_) {
            $slice = array_slice($vals, max(0, $i - $window + 1), min($window, $i + 1));
            $out[] = round(array_sum($slice) / count($slice), 1);
        }

        return $out;
    }

    /** True for days more than ANOMALY_Z standard deviations from the mean. */
    private function anomalyFlags(array $vals): array
    {
        $n = count($vals);
        $flags = array_fill(0, $n, false);
        if ($n < 7) {
            return $flags;
        }

        $mean = array_sum($vals) / $n;
        $std = sqrt(array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $vals)) / ($n - 1));
        if ($std <= 0) {
            return $flags;
        }

        foreach ($vals as $i => $v) {
            $flags[$i] = abs(($v - $mean) / $std) > self::ANOMALY_Z;
        }

        return $flags;
    }

    // ---------------- Inventory: diagnostic + prescriptive ----------------

    private function inventory(?int $branchId, array $names): array
    {
        $items = Inventory::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('branch_id')
            ->orderBy('item_name')
            ->get();

        $usage = $this->dailyUsage($branchId);
        $rows = [];
        $low = 0;

        foreach ($items as $item) {
            $stock = (int) $item->stock_qty;
            $reorder = (int) ($item->reorder_level ?? self::DEFAULT_REORDER);
            $daily = $usage[$item->branch_id . '|' . $item->item_name] ?? null;
            $cover = ($daily && $daily > 0) ? round($stock / $daily, 1) : null;
            $status = $this->stockStatus($stock, $reorder, $cover);

            if ($status !== 'OK') {
                $low++;
            }

            $rows[] = [
                'branch' => $names[$item->branch_id] ?? "Branch {$item->branch_id}",
                'item' => $item->item_name,
                'stock' => $stock,
                'reorder' => $reorder,
                'cover' => $cover,
                'status' => $status,
                'suggest' => $daily ? max(0, (int) round($daily * self::COVER_DAYS_TARGET - $stock)) : 0,
                'updated' => (string) $item->update_date,
            ];
        }

        return ['rows' => $rows, 'low_count' => $low];
    }

    /** Average units used per day over the last 14 days, keyed "branch|item". */
    private function dailyUsage(?int $branchId): array
    {
        if (!Schema::hasTable('inventory_log')) {
            return [];
        }

        $usage = [];
        InventoryLog::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereRaw('log_date >= CURRENT_DATE - 14')
            ->selectRaw('branch_id, item_name, SUM(qty_used) / 14.0 AS daily')
            ->groupBy('branch_id', 'item_name')
            ->get()
            ->each(function ($r) use (&$usage) {
                $usage[$r->branch_id . '|' . $r->item_name] = (float) $r->daily;
            });

        return $usage;
    }

    private function stockStatus(int $stock, int $reorder, ?float $cover): string
    {
        if ($stock <= $reorder || ($cover !== null && $cover <= 3)) {
            return 'Critical';
        }
        if ($cover !== null && $cover <= 7) {
            return 'Low';
        }

        return 'OK';
    }

    private function inventoryChart(array $rows, bool $multi): array
    {
        $colors = ['Critical' => '#c0392b', 'Low' => '#f39c12', 'OK' => '#2ea347'];

        return [
            'labels' => array_map(fn ($x) => $multi ? "{$x['item']} ({$x['branch']})" : $x['item'], $rows),
            'stock' => array_column($rows, 'stock'),
            'reorder' => array_column($rows, 'reorder'),
            'colors' => array_map(fn ($x) => $colors[$x['status']], $rows),
        ];
    }

    // ---------------- Branch comparison ----------------

    private function comparison(array $inventoryRows, array $names): array
    {
        $sales = Sale::query()
            ->selectRaw('branch_id, SUM(quantity) AS sold, SUM(total_amount) AS rev')
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        $lowByBranch = [];
        foreach ($inventoryRows as $x) {
            if ($x['status'] !== 'OK') {
                $lowByBranch[$x['branch']] = ($lowByBranch[$x['branch']] ?? 0) + 1;
            }
        }

        return Production::query()
            ->selectRaw('branch_id, SUM(actual_quantity) AS prod')
            ->groupBy('branch_id')
            ->get()
            ->map(function ($r) use ($sales, $names, $lowByBranch) {
                $id = (int) $r->branch_id;
                $name = $names[$id] ?? "Branch {$id}";
                $produced = (float) $r->prod;
                $sold = (float) ($sales->get($id)?->sold ?? 0);
                $revenue = (float) ($sales->get($id)?->rev ?? 0);

                return [
                    'branch' => $name,
                    'id' => $id,
                    'produced' => (int) $produced,
                    'revenue' => round($revenue, 2),
                    'sell_through' => ($produced && $sold) ? round($sold / $produced * 100, 1) : null,
                    'low' => $lowByBranch[$name] ?? 0,
                ];
            })
            ->sortByDesc('produced')
            ->values()
            ->all();
    }

    // ---------------- Prescriptive recommendations ----------------

    private function recommendations(array $rows, array $kpi, array $anomalies, array $comp): array
    {
        $recs = [];

        foreach ($rows as $x) {
            if ($x['status'] !== 'Critical') {
                continue;
            }
            $extra = $x['suggest'] ? " Order about {$x['suggest']} units (14-day supply)." : '';
            $when = $x['cover'] !== null
                ? "{$x['cover']} days of cover left."
                : "Stock ({$x['stock']}) is at/below reorder level ({$x['reorder']}).";
            $recs[] = $this->rec('danger', "Restock {$x['item']} at {$x['branch']}: {$when}{$extra}");
        }

        if ($kpi['change'] !== null && $kpi['change'] <= -15) {
            $recs[] = $this->rec('warning', 'Production fell ' . abs($kpi['change']) . '% vs the previous 7 days. Check marinade/feed stock and staffing for the same dates.');
        }
        if ($kpi['change'] !== null && $kpi['change'] >= 15) {
            $recs[] = $this->rec('success', "Production is up {$kpi['change']}% vs the previous 7 days. Make sure marinade and packaging stock can keep up.");
        }
        if ($anomalies) {
            $a = end($anomalies);
            $recs[] = $this->rec('warning', "Unusual production on {$a['date']} ({$a['qty']} pcs). Verify the entry or find out what happened that day.");
        }
        if ($kpi['forecast7']) {
            $recs[] = $this->rec('info', "Expected production for the next 7 days is about {$kpi['forecast7']} chickens. Prepare marinade and packaging accordingly.");
        }

        if (count($comp) > 1) {
            $hi = $comp[0];
            $lo = end($comp);
            if ($hi['produced'] > 0 && $lo['produced'] < $hi['produced'] * 0.7) {
                $pct = round((1 - $lo['produced'] / $hi['produced']) * 100);
                $recs[] = $this->rec('info', "{$lo['branch']} produces {$pct}% less than {$hi['branch']}. Review demand, location, and stock allocation.");
            }
        }

        foreach ($comp as $c) {
            if ($c['sell_through'] !== null && $c['sell_through'] < 80) {
                $recs[] = $this->rec('warning', "{$c['branch']} sold only {$c['sell_through']}% of what it produced. Consider lowering the daily production target.");
            }
        }

        return $recs ?: [$this->rec('success', 'No issues detected. Keep logging daily production and inventory usage so insights stay accurate.')];
    }

    private function rec(string $level, string $text): array
    {
        return ['level' => $level, 'text' => $text];
    }
}