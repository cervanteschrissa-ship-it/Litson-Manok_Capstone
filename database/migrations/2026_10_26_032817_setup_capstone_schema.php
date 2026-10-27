<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Capstone schema: branches, production, inventory, inventory_log, sales.
 * Safe on an existing database: tables are created only if missing,
 * and constraints/indexes are added only if they do not exist yet.
 */
return new class extends Migration
{
    /** [table, constraint name, definition] */
    private array $constraints = [
        ['inventory',     'chk_inventory_stock_nonneg',    'CHECK (stock_qty >= 0)'],
        ['inventory',     'chk_inventory_reorder_nonneg',  'CHECK (reorder_level >= 0)'],
        ['inventory_log', 'chk_log_qty_positive',          'CHECK (qty_used > 0)'],
        ['production',    'chk_production_actual_nonneg',  'CHECK (actual_quantity >= 0)'],
        ['production',    'chk_production_planned_nonneg', 'CHECK (planned_quantity >= 0)'],
        ['sales',         'chk_sales_quantity_positive',   'CHECK (quantity > 0)'],
        ['sales',         'chk_sales_amount_nonneg',       'CHECK (total_amount >= 0)'],
    ];

    /** Columns that must never be empty. */
    private array $notNull = [
        'branches'      => ['branch_name'],
        'production'    => ['branch_id', 'production_date', 'actual_quantity'],
        'inventory'     => ['branch_id', 'item_name', 'stock_qty'],
        'inventory_log' => ['branch_id', 'item_name', 'qty_used'],
        'sales'         => ['branch_id', 'sale_date', 'quantity', 'total_amount'],
    ];

    private array $indexes = [
        'idx_production_branch_date'    => 'production (branch_id, production_date)',
        'idx_sales_branch_date'         => 'sales (branch_id, sale_date)',
        'idx_inventory_log_branch_date' => 'inventory_log (branch_id, log_date)',
    ];

    public function up(): void
    {
        $this->createTables();
        $this->addNotNull();
        $this->addConstraints();
        $this->addIndexes();
    }

    public function down(): void
    {
        // The tables existed before this migration, so only undo what it adds.
        foreach ($this->constraints as [$table, $name]) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
        }
        foreach (array_keys($this->indexes) as $name) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }

    private function createTables(): void
    {
        if (!Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $t) {
                $t->increments('branch_id');
                $t->string('branch_name', 100);
                $t->string('location', 100)->nullable();
            });
        }

        if (!Schema::hasTable('production')) {
            Schema::create('production', function (Blueprint $t) {
                $t->increments('production_id');
                $t->integer('branch_id');
                $t->date('production_date');
                $t->string('product', 50)->nullable();
                $t->integer('planned_quantity')->nullable();
                $t->integer('actual_quantity');
                $t->foreign('branch_id')->references('branch_id')->on('branches');
            });
        }

        if (!Schema::hasTable('sales')) {
            Schema::create('sales', function (Blueprint $t) {
                $t->increments('sale_id');
                $t->integer('branch_id');
                $t->date('sale_date');
                $t->string('product', 50)->nullable();
                $t->integer('quantity');
                $t->decimal('total_amount', 10, 2);
                $t->foreign('branch_id')->references('branch_id')->on('branches');
            });
        }

        if (!Schema::hasTable('inventory')) {
            Schema::create('inventory', function (Blueprint $t) {
                $t->increments('inventory_id');
                $t->integer('branch_id');
                $t->string('item_name', 100);
                $t->integer('stock_qty');
                $t->date('update_date')->nullable();
                $t->integer('reorder_level')->default(10);
                $t->unique(['branch_id', 'item_name'], 'inventory_branch_id_item_name_key');
                $t->foreign('branch_id')->references('branch_id')->on('branches');
            });
        } elseif (!Schema::hasColumn('inventory', 'reorder_level')) {
            Schema::table('inventory', function (Blueprint $t) {
                $t->integer('reorder_level')->default(10);
            });
        }

        if (!Schema::hasTable('inventory_log')) {
            Schema::create('inventory_log', function (Blueprint $t) {
                $t->increments('log_id');
                $t->integer('branch_id');
                $t->string('item_name', 100);
                $t->integer('qty_used');
                $t->date('log_date')->default(DB::raw('CURRENT_DATE'));
                $t->foreign('branch_id')->references('branch_id')->on('branches');
            });
        }
    }

    private function addNotNull(): void
    {
        foreach ($this->notNull as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET NOT NULL");
            }
        }
    }

    private function addConstraints(): void
    {
        foreach ($this->constraints as [$table, $name, $definition]) {
            $exists = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);
            if (!$exists) {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} {$definition}");
            }
        }
    }

    private function addIndexes(): void
    {
        foreach ($this->indexes as $name => $target) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$target}");
        }
    }
};