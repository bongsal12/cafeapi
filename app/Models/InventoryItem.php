<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'image',
        'current_stock',
        'unit',
        'low_stock_alert',
        'is_active',
    ];

    protected $casts = [
        'current_stock' => 'float',
        'low_stock_alert' => 'float',
        'is_active' => 'boolean',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'inventory_item_id');
    }
}
