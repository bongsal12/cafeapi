<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPayment extends Model
{
    protected $fillable = [
        'order_id','provider','status','amount','currency',
        'qr_string','bakong_trx_id','merchant_ref','expires_at','raw'
    ];

    protected $casts = [
        'raw' => 'array',
        'expires_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
