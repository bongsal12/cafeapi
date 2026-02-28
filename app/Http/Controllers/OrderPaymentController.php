<?php
// app/Http/Controllers/OrderPaymentController.php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Services\BakongService;
use Illuminate\Http\Request;

class OrderPaymentController extends Controller
{
    public function khqr(Request $request, Order $order, BakongService $bakong)
    {
        if ($order->payment_status === 'paid') {
            return response()->json([
                'message' => 'Order already paid',
                'order_id' => $order->id,
                'reference' => $order->reference,
                'paid' => true,
            ], 200);
        }

        // If you want to reuse existing pending payment (optional)
        $latest = $order->payments()->latest()->first();
        if ($latest && $latest->status === 'pending' && $latest->expires_at && $latest->expires_at->isFuture()) {
            return response()->json([
                'order_id' => $order->id,
                'reference' => $order->reference,
                'amount' => (float) $order->total,
                'currency' => 'USD',
                'payment_id' => $latest->id,
                'expires_at' => $latest->expires_at,
                'qr_string' => $latest->qr_string,
                'md5' => $order->khqr_md5,
                'full_hash' => $order->bakong_full_hash,
            ]);
        }

        $gen = $bakong->generateKhqr(
            amount: (float) $order->total,
            merchantRef: (string) $order->reference,
            tableNo: $request->input('table_no')
        );

        $payment = OrderPayment::create([
            'order_id' => $order->id,
            'provider' => 'bakong',
            'status' => 'pending',
            'amount' => (float) $order->total,
            'currency' => 'USD',
            'qr_string' => $gen['qr_string'],
            'merchant_ref' => $order->reference,
            'expires_at' => $gen['expires_at'],
            'raw' => null,
        ]);

        $order->update([
            'payment_provider' => 'bakong',
            'payment_method' => 'khqr',
            'payment_status' => 'pending',
            'currency' => 'USD',
            'khqr_string' => $gen['qr_string'],
            'khqr_md5' => $gen['md5'],
            'bakong_full_hash' => $gen['full_hash'],
            'payment_expires_at' => $gen['expires_at'],
            'payment_ref' => $payment->merchant_ref,
        ]);

        return response()->json([
            'order_id' => $order->id,
            'reference' => $order->reference,
            'amount' => (float) $order->total,
            'currency' => 'USD',
            'payment_id' => $payment->id,
            'expires_at' => $payment->expires_at,
            'qr_string' => $payment->qr_string,
            'md5' => $order->khqr_md5,
            'full_hash' => $order->bakong_full_hash,
        ]);
    }
}
