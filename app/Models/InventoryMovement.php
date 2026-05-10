<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Product;
use App\Models\InventoryItem;
use App\Models\User;

class InventoryMovement extends Model
{
    protected $fillable = [
        'product_id',
        'inventory_item_id',
        'type',
        'quantity',
        'unit',
        'before_stock',
        'after_stock',
        'note',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'float',
        'before_stock' => 'float',
        'after_stock' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
