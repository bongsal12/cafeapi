<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

use App\Models\Category;

class Promotion extends Model
{
    protected $fillable = [
        'name',
        'scope_type',
        'product_id',
        'category_id',
        'percent',
        'apply_to_variants',
        'start_at',
        'end_at',
        'active',
    ];

    protected $casts = [
        'percent' => 'integer',
        'apply_to_variants' => 'boolean',
        'active' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getStatusAttribute(): string
    {
        $now = Carbon::now();

        if (!$this->active) {
            return 'expired';
        }

        if ($this->start_at && $now->lt($this->start_at)) {
            return 'scheduled';
        }

        if ($this->end_at && $now->gt($this->end_at)) {
            return 'expired';
        }

        return 'active';
    }

    public function getScopeLabelAttribute(): string
    {
        if ($this->scope_type === 'category') {
            return $this->category?->name ?? ('Category #' . $this->category_id);
        }

        return $this->product?->name ?? ('Product #' . $this->product_id);
    }
}
