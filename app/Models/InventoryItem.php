<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $fillable = [
        'name',
        'category',
        'slug',
        'image',
        'current_stock',
        'unit',
        'low_stock_alert',
        'cost_per_unit',
        'sweetness_levels',
        'is_active',
    ];

    protected $casts = [
        'current_stock' => 'float',
        'low_stock_alert' => 'float',
        'cost_per_unit' => 'float',
        'sweetness_levels' => 'array',
        'is_active' => 'boolean',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'inventory_item_id');
    }
}
