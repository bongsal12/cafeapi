<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryRecipe extends Model
{
    protected $fillable = [
        'product_id',
        'size',
        'description',
        'selling_price',
        'total_cost',
        'sweetness_options',
        'is_active',
    ];

    protected $casts = [
        'selling_price' => 'float',
        'total_cost' => 'float',
        'sweetness_options' => 'array',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryRecipeItem::class, 'inventory_recipe_id');
    }
}
