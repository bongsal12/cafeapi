<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountItem extends Model
{
    protected $fillable = [
        'stock_count_session_id',
        'inventory_item_id',
        'system_stock',
        'actual_count',
        'difference',
        'reason',
        'status',
        'movement_id',
    ];

    protected $casts = [
        'system_stock' => 'float',
        'actual_count' => 'float',
        'difference' => 'float',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(StockCountSession::class, 'stock_count_session_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
