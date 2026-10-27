<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\Production;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    /** Starter items that always appear in the staff dropdown. */
    private const ITEMS = [
        'Pouch',
        'Marinade',
        'Fresh Chicken',
        'Large Plastic',
    ];

    /** Production totals per branch. */
    public function home()
    {
        $summary_records = Branch::withSum('production as total_chickens', 'actual_quantity')
            ->orderBy('branch_id')
            ->get()
            ->each(fn ($b) => $b->total_chickens = $b->total_chickens ?? 0);

        return view('home', compact('summary_records'));
    }

    /** Production and inventory page for one branch. */
    public function show($id)
    {
        $branch = Branch::findOrFail($id);

        // Inventory Records: current stock rows (green) + used-stock log rows (red), newest first
        $activity = $branch->inventory()->get()
            ->map(fn ($r) => [
                'date' => (string) $r->update_date,
                'item' => $r->item_name,
                'qty'  => $r->stock_qty,
                'used' => false,
            ])
            ->concat(
                InventoryLog::where('branch_id', $id)->get()
                    ->map(fn ($l) => [
                        'date' => (string) $l->log_date,
                        'item' => $l->item_name,
                        'qty'  => $l->qty_used,
                        'used' => true,
                    ])
            )
            ->sortByDesc('date')
            ->values();

        return view('branch', [
            'inventoryItems' => $this->itemNames(),
            'branch_id' => $branch->branch_id,
            'branch' => $branch,
            'production_records' => $branch->production()->orderBy('production_date')->get(),
            'inventory_records' => $branch->inventory()->orderBy('item_name')->get(),
            'inventory_activity' => $activity,
        ]);
    }

    /** Record today's production for a branch. */
    public function addProduction(Request $r)
    {
        $data = $r->validate([
            'branch_id' => 'required|integer|exists:branches,branch_id',
            'chicken_qty' => 'required|integer|min:0',
            'product' => 'nullable|string|max:100',
            'planned_quantity' => 'nullable|integer|min:0',
        ]);

        Production::create([
            'branch_id' => $data['branch_id'],
            'production_date' => now()->toDateString(),
            'product' => $data['product'] ?? 'Litson Manok',
            'planned_quantity' => $data['planned_quantity'] ?? $data['chicken_qty'],
            'actual_quantity' => $data['chicken_qty'],
        ]);

        return $this->toBranch($data['branch_id'], 'Production saved.');
    }

    /**
     * Add stock to an item.
     * Owner: can type any item name (creates new items).
     * Staff: can only pick an item that already exists in the dropdown.
     */
    public function addInventory(Request $r)
    {
        $isOwner = auth()->user()->role === 'owner';

        $data = $r->validate([
            'branch_id' => 'required|integer|exists:branches,branch_id',
            'item_name' => $isOwner
                ? ['required', 'string', 'max:100']
                : ['required', Rule::in($this->itemNames())],
            'stock_qty' => 'required|integer|min:1',
        ]);

        // Clean the name so "fresh chicken" and "Fresh Chicken" are the same item
        $item = trim(ucwords(strtolower($data['item_name'])));

        $row = Inventory::firstOrNew(['branch_id' => $data['branch_id'], 'item_name' => $item]);
        $row->stock_qty = ($row->exists ? $row->stock_qty : 0) + $data['stock_qty'];
        $row->update_date = now()->toDateString();
        $row->save();

        return $this->toBranch($data['branch_id'], "Added {$data['stock_qty']} to {$item}.");
    }

    /** Use stock: refuses unknown items and quantities above what is available. */
    public function useInventory(Request $r)
    {
        $data = $r->validate([
            'branch_id' => 'required|integer|exists:branches,branch_id',
            'item_name' => 'required|string|max:100',
            'qty_used' => 'required|integer|min:1',
        ]);
        $item = trim($data['item_name']);

        [$status, $value] = DB::transaction(function () use ($data, $item) {
            // Lock the row so two users cannot use the same stock at once
            $row = Inventory::where('branch_id', $data['branch_id'])
                ->where('item_name', $item)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                return ['missing', 0];
            }
            if ($row->stock_qty < $data['qty_used']) {
                return ['insufficient', $row->stock_qty];
            }

            $row->update([
                'stock_qty' => $row->stock_qty - $data['qty_used'],
                'update_date' => now()->toDateString(),
            ]);

            InventoryLog::create([
                'branch_id' => $data['branch_id'],
                'item_name' => trim(ucwords(strtolower($data['item_name']))),
                'qty_used' => $data['qty_used'],
                'log_date' => now()->toDateString(),
            ]);

            return ['ok', $row->stock_qty];
        });

        $back = "/branch/{$data['branch_id']}";

        if ($status === 'missing') {
            return redirect($back)->withInput()
                ->withErrors(['item_name' => "\"{$item}\" does not exist in this branch."]);
        }
        if ($status === 'insufficient') {
            return redirect($back)->withInput()
                ->withErrors(['qty_used' => "Not enough stock: only {$value} of {$item} available."]);
        }

        return $this->toBranch($data['branch_id'], "Used {$data['qty_used']} of {$item}. Remaining: {$value}.");
    }

    /** Redirect to a branch page with a success message. */
    private function toBranch(int|string $branchId, string $message)
    {
        return redirect("/branch/{$branchId}")->with('success', $message);
    }

    /** Starter items + every item name already saved (including ones the owner added). */
    private function itemNames(): array
    {
        $saved = Inventory::query()->distinct()->pluck('item_name')->all();

        $items = array_unique(array_merge(self::ITEMS, $saved));
        sort($items);

        return $items;
    }
}