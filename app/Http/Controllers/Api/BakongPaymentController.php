<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Services\BakongService;

class BakongPaymentController extends Controller
{
    public function status(Order $order, BakongService $bakong)
    {
        if (!$order->khqr_md5) {
            return response()->json([
                'paid' => false,
                'message' => 'Order has no khqr_md5 yet. Call /bakong/khqr first.',
            ], 400);
        }

        // If already paid
        if ($order->payment_status === 'paid') {
            return response()->json(['paid' => true, 'order' => $order]);
        }

       $check = $bakong->checkKhqr(['md5' => $order->khqr_md5]);


        // You must adapt "isPaid" based on real response structure.
        // For now, we detect paid if response contains "paid" or "success" flags.
        $data = $check['data'] ?? [];
        $isPaid = false;

        if (is_array($data)) {
            $isPaid =
                ($data['paid'] ?? false) === true
                || ($data['status'] ?? null) === 'SUCCESS'
                || ($data['responseCode'] ?? null) === 0;
        }

        if ($isPaid) {
            $order->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
            ]);

            // Update latest payment row too
            OrderPayment::where('order_id', $order->id)
                ->where('provider', 'bakong')
                ->latest('id')
                ->first()?->update([
                    'status' => 'paid',
                    'raw' => $data,
                ]);

            return response()->json(['paid' => true, 'order' => $order, 'bakong' => $data]);
        }

        return response()->json(['paid' => false, 'bakong' => $data, 'raw' => $check['raw']]);
    }
}
