<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $table = 'branches';
    protected $primaryKey = 'branch_id';
    public $timestamps = false;

    protected $fillable = ['branch_name', 'location'];

    public function production(): HasMany
    {
        return $this->hasMany(Production::class, 'branch_id', 'branch_id');
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class, 'branch_id', 'branch_id');
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class, 'branch_id', 'branch_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'branch_id', 'branch_id');
    }
}