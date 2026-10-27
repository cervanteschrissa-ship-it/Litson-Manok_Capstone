<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Production extends Model
{
    protected $table = 'production';
    protected $primaryKey = 'production_id';
    public $timestamps = false;

    protected $fillable = ['branch_id', 'production_date', 'product', 'planned_quantity', 'actual_quantity'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    /** Alias so views and charts can keep using "chicken_qty". */
    protected function chickenQty(): Attribute
    {
        return Attribute::get(fn () => $this->actual_quantity);
    }
}