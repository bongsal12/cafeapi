<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'reference', 'status', 'total', 'items',
        'payment_method', 'payment_status', 'currency',
        'khqr_string', 'khqr_md5', 'bakong_full_hash',
        'payment_expires_at', 'paid_at',
        'payment_provider', 'payment_ref',
    ];

    protected $casts = [
        'items' => 'array',
        'total' => 'decimal:2',
        'payment_expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function payments()
    {
        return $this->hasMany(OrderPayment::class);
    }
}
