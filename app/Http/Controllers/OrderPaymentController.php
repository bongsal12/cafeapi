<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Services\BakongService;
use Illuminate\Http\Request;

class OrderPaymentController extends Controller
{
    public function khqr(Request $request, Order $order, BakongService $bakong)
    {
        // Only USD (your requirement)
        $currency = 'USD';

        // Create local KHQR payload
        $gen = $bakong->localGenerateKhqr([
            'amount' => (float) $order->total,
            'table_no' => $request->input('table_no'),
            'merchant_ref' => $order->reference,
        ]);

        if (empty($gen['qr_string'])) {
            return response()->json([
                'message' => 'KHQR generation failed (qr_string missing)',
                'data' => $gen,
            ], 500);
        }

        // Save in order_payments
        $payment = OrderPayment::create([
            'order_id' => $order->id,
            'provider' => 'bakong',
            'status' => 'pending',
            'amount' => (float) $order->total,
            'currency' => $currency,
            'qr_string' => $gen['qr_string'],
            'merchant_ref' => $gen['merchant_ref'] ?? $order->reference,
            'expires_at' => $gen['expires_at'],
            'raw' => $gen['raw'] ?? null,
        ]);

        // Save into orders (so you can fetch quickly)
        $order->update([
            'payment_provider' => 'bakong',
            'payment_method' => 'khqr',
            'payment_status' => 'pending',
            'currency' => $currency,
            'khqr_string' => $gen['qr_string'],
            'khqr_md5' => $gen['md5'] ?? null,
            'bakong_full_hash' => $gen['full_hash'] ?? null,
            'payment_expires_at' => $gen['expires_at'],
            'payment_ref' => $payment->merchant_ref,
        ]);

        return response()->json([
            'order_id' => $order->id,
            'reference' => $order->reference,
            'amount' => (float) $order->total,
            'currency' => $currency,
            'payment_id' => $payment->id,
            'expires_at' => $payment->expires_at,
            'qr_string' => $payment->qr_string,
            'md5' => $order->khqr_md5,
            'full_hash' => $order->bakong_full_hash,
        ]);
    }
}
