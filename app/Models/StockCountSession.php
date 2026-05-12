<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCountSession extends Model
{
    protected $fillable = [
        'count_date',
        'branch',
        'counted_by',
        'note',
        'status',
        'approved_by',
    ];

    protected $casts = [
        'count_date' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class, 'stock_count_session_id');
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
