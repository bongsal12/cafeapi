<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryRecipeItem extends Model
{
    protected $fillable = [
        'inventory_recipe_id',
        'inventory_item_id',
        'quantity_used',
        'unit',
    ];

    protected $casts = [
        'quantity_used' => 'float',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(InventoryRecipe::class, 'inventory_recipe_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
