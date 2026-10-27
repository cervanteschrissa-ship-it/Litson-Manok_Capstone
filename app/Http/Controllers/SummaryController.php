<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SummaryController extends Controller
{
    public function index(Request $request)
    {
        $selected = $request->query('branch', 'all');
        $bid = ctype_digit((string) $selected) ? (int) $selected : null;

        // Branch names + dropdown
        $names = DB::table('branches')->orderBy('branch_id')->pluck('branch_name', 'branch_id')->all();
        $bname = fn ($id) => $names[$id] ?? "Branch $id";
        $branch_list = [];
        foreach ($names as $id => $n) {
            $branch_list[] = ['id' => (int) $id, 'name' => $n];
        }

        // ---------- Production (descriptive + predictive) ----------
        $q = DB::table('production')
            ->selectRaw('production_date AS d, SUM(actual_quantity) AS qty')
            ->groupBy('production_date')->orderBy('production_date');
        if ($bid) $q->where('branch_id', $bid);
        $prod = $q->get();

        $chart = ['labels' => [], 'qty' => [], 'ma7' => [], 'anom' => [], 'fc_labels' => [], 'fc' => []];
        $kpi = ['total' => 0, 'avg' => 0, 'change' => null, 'forecast7' => 0, 'anomalies' => 0];
        $anomalies = [];

        if ($prod->isNotEmpty()) {
            $byDate = [];
            foreach ($prod as $r) $byDate[$r->d] = (float) $r->qty;

            $series = [];
            foreach (CarbonPeriod::create($prod->first()->d, $prod->last()->d) as $day) {
                $k = $day->toDateString();
                $series[$k] = $byDate[$k] ?? 0.0;
            }
            $series = array_slice($series, -60, null, true);
            $dates = array_keys($series);
            $vals = array_values($series);
            $n = count($vals);

            // 7-day moving average
            $ma7 = [];
            for ($i = 0; $i < $n; $i++) {
                $w = array_slice($vals, max(0, $i - 6), min(7, $i + 1));
                $ma7[] = round(array_sum($w) / count($w), 1);
            }

            // Anomaly detection: |z| > 2
            $mean = array_sum($vals) / $n;
            $std = $n > 1 ? sqrt(array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $vals)) / ($n - 1)) : 0;
            $flags = array_fill(0, $n, false);
            if ($n >= 7 && $std > 0) {
                foreach ($vals as $i => $v) $flags[$i] = abs(($v - $mean) / $std) > 2;
            }
            foreach ($flags as $i => $f) {
                if ($f) $anomalies[] = ['date' => Carbon::parse($dates[$i])->format('M d'), 'qty' => (int) $vals[$i]];
            }

            // 7-day forecast (average of last 7 days)
            $base = array_sum(array_slice($vals, -7)) / min(7, $n);
            $fcLabels = [];
            $last = Carbon::parse(end($dates));
            for ($i = 1; $i <= 7; $i++) $fcLabels[] = $last->copy()->addDays($i)->format('M d');

            $chart = [
                'labels' => array_map(fn ($d) => Carbon::parse($d)->format('M d'), $dates),
                'qty' => array_map('intval', $vals),
                'ma7' => $ma7,
                'anom' => $flags,
                'fc_labels' => $fcLabels,
                'fc' => array_fill(0, 7, round($base, 1)),
            ];

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
                'forecast7' => (int) round($base * 7),
                'anomalies' => count($anomalies),
            ];
        }

        // ---------- Inventory (diagnostic + prescriptive) ----------
        $iq = DB::table('inventory')->orderBy('branch_id')->orderBy('item_name');
        if ($bid) $iq->where('branch_id', $bid);
        $inv = $iq->get();

        $has_log = Schema::hasTable('inventory_log');
        $usage = [];
        if ($has_log) {
            $uq = DB::table('inventory_log')
                ->selectRaw('branch_id, item_name, SUM(qty_used) / 14.0 AS daily')
                ->whereRaw('log_date >= CURRENT_DATE - 14');
            if ($bid) $uq->where('branch_id', $bid);
            foreach ($uq->groupBy('branch_id', 'item_name')->get() as $r) {
                $usage[$r->branch_id . '|' . $r->item_name] = (float) $r->daily;
            }
        }

        $inventory_rows = [];
        $low_count = 0;
        foreach ($inv as $r) {
            $stock = (int) $r->stock_qty;
            $reorder = (int) ($r->reorder_level ?? 10);
            $daily = $usage[$r->branch_id . '|' . $r->item_name] ?? null;
            $cover = ($daily && $daily > 0) ? round($stock / $daily, 1) : null;

            if ($stock <= $reorder || ($cover !== null && $cover <= 3)) $status = 'Critical';
            elseif ($cover !== null && $cover <= 7) $status = 'Low';
            else $status = 'OK';
            if ($status !== 'OK') $low_count++;

            $inventory_rows[] = [
                'branch' => $bname($r->branch_id), 'item' => $r->item_name, 'stock' => $stock,
                'reorder' => $reorder, 'cover' => $cover, 'status' => $status,
                'suggest' => $daily ? max(0, (int) round($daily * 14 - $stock)) : 0,
                'updated' => (string) $r->update_date,
            ];
        }

        $multi = $bid === null;
        $colors = ['Critical' => '#c0392b', 'Low' => '#f39c12', 'OK' => '#2ea347'];
        $inv_chart = [
            'labels' => array_map(fn ($x) => $multi ? "{$x['item']} ({$x['branch']})" : $x['item'], $inventory_rows),
            'stock' => array_column($inventory_rows, 'stock'),
            'reorder' => array_column($inventory_rows, 'reorder'),
            'colors' => array_map(fn ($x) => $colors[$x['status']], $inventory_rows),
        ];

        // ---------- Branch comparison ----------
        $smap = DB::table('sales')
            ->selectRaw('branch_id, SUM(quantity) AS sold, SUM(total_amount) AS rev')
            ->groupBy('branch_id')->get()->keyBy('branch_id');
        $lowBy = [];
        foreach ($inventory_rows as $x) {
            if ($x['status'] !== 'OK') $lowBy[$x['branch']] = ($lowBy[$x['branch']] ?? 0) + 1;
        }
        $comp = [];
        $pAll = DB::table('production')->selectRaw('branch_id, SUM(actual_quantity) AS prod')->groupBy('branch_id')->get();
        foreach ($pAll as $r) {
            $b = (int) $r->branch_id;
            $produced = (float) $r->prod;
            $sold = (float) ($smap[$b]->sold ?? 0);
            $rev = (float) ($smap[$b]->rev ?? 0);
            $comp[] = [
                'branch' => $bname($b), 'id' => $b, 'produced' => (int) $produced,
                'revenue' => round($rev, 2),
                'sell_through' => ($produced && $sold) ? round($sold / $produced * 100, 1) : null,
                'low' => $lowBy[$bname($b)] ?? 0,
            ];
        }
        usort($comp, fn ($a, $b) => $b['produced'] <=> $a['produced']);
        $kpi['revenue'] = round(array_sum(array_map(
            fn ($c) => ($bid === null || $c['id'] === $bid) ? $c['revenue'] : 0, $comp
        )), 2);

        // ---------- Prescriptive recommendations ----------
        $recs = [];
        foreach ($inventory_rows as $x) {
            if ($x['status'] !== 'Critical') continue;
            $extra = $x['suggest'] ? " Order about {$x['suggest']} units (14-day supply)." : '';
            $when = $x['cover'] !== null
                ? "{$x['cover']} days of cover left."
                : "Stock ({$x['stock']}) is at/below reorder level ({$x['reorder']}).";
            $recs[] = ['level' => 'danger', 'text' => "Restock {$x['item']} at {$x['branch']}: $when$extra"];
        }
        if ($kpi['change'] !== null && $kpi['change'] <= -15) {
            $recs[] = ['level' => 'warning', 'text' => 'Production fell ' . abs($kpi['change']) . '% vs the previous 7 days. Check marinade/feed stock and staffing for the same dates.'];
        }
        if ($kpi['change'] !== null && $kpi['change'] >= 15) {
            $recs[] = ['level' => 'success', 'text' => "Production is up {$kpi['change']}% vs the previous 7 days. Make sure marinade and packaging stock can keep up."];
        }
        if ($anomalies) {
            $a = end($anomalies);
            $recs[] = ['level' => 'warning', 'text' => "Unusual production on {$a['date']} ({$a['qty']} pcs). Verify the entry or find out what happened that day."];
        }
        if ($kpi['forecast7']) {
            $recs[] = ['level' => 'info', 'text' => "Expected production for the next 7 days is about {$kpi['forecast7']} chickens. Prepare marinade and packaging accordingly."];
        }
        if (count($comp) > 1) {
            $lo = end($comp);
            $hi = $comp[0];
            if ($hi['produced'] > 0 && $lo['produced'] < $hi['produced'] * 0.7) {
                $pct = round((1 - $lo['produced'] / $hi['produced']) * 100);
                $recs[] = ['level' => 'info', 'text' => "{$lo['branch']} produces $pct% less than {$hi['branch']}. Review demand, location, and stock allocation."];
            }
        }
        foreach ($comp as $c) {
            if ($c['sell_through'] !== null && $c['sell_through'] < 80) {
                $recs[] = ['level' => 'warning', 'text' => "{$c['branch']} sold only {$c['sell_through']}% of what it produced. Consider lowering the daily production target."];
            }
        }
        if (!$recs) {
            $recs[] = ['level' => 'success', 'text' => 'No issues detected. Keep logging daily production and inventory usage so insights stay accurate.'];
        }

        return view('summary', compact(
            'branch_list', 'selected', 'kpi', 'low_count', 'chart', 'inv_chart',
            'inventory_rows', 'comp', 'anomalies', 'recs', 'has_log'
        ));
    }
}