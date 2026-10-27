<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLog extends Model
{
    protected $table = 'inventory_log';
    protected $primaryKey = 'log_id';
    public $timestamps = false;

    protected $fillable = ['branch_id', 'item_name', 'qty_used', 'log_date'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }
}