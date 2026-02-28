<?php
// app/Http/Controllers/Api/BakongPaymentController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Services\BakongService;

class BakongPaymentController extends Controller
{
    public function status(Order $order, BakongService $bakong)
    {
        $order->refresh();

        // already paid
        if ($order->payment_status === 'paid') {
            return response()->json([
                'paid' => true,
                'payment_status' => 'paid',
                'paid_at' => $order->paid_at,
                'status' => $order->status,
                'bakong' => null,
            ]);
        }

        if (!$order->khqr_md5) {
            return response()->json([
                'paid' => false,
                'payment_status' => $order->payment_status ?? 'pending',
                'paid_at' => $order->paid_at,
                'status' => $order->status ?? 'pending',
                'bakong' => ['responseCode' => 1, 'responseMessage' => 'Missing md5'],
            ]);
        }

        $bakongResp = $bakong->checkByMd5($order->khqr_md5);

        $isPaid = $bakong->isPaidFromCheck(
            bakongResp: $bakongResp,
            expectedAmount: (float) $order->total,
            expectedToAccountId: (string) config('services.bakong.account_id')
        );

        if ($isPaid) {
            $now = now();

            $order->update([
                'payment_status' => 'paid',
                'paid_at' => $now,
                'status' => 'paid',
            ]);

            // update latest payment record
            $payment = OrderPayment::where('order_id', $order->id)->latest()->first();
            if ($payment) {
                $payment->update([
                    'status' => 'paid',
                    'bakong_trx_id' => $bakongResp['data']['externalRef'] ?? null,
                    'raw' => $bakongResp,
                ]);
            }

            return response()->json([
                'paid' => true,
                'payment_status' => 'paid',
                'paid_at' => $order->paid_at,
                'status' => $order->status,
                'bakong' => $bakongResp,
            ]);
        }

        return response()->json([
            'paid' => false,
            'payment_status' => $order->payment_status ?? 'pending',
            'paid_at' => $order->paid_at,
            'status' => $order->status ?? 'pending',
            'bakong' => $bakongResp,
        ]);
    }
}
