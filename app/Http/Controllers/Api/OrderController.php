<?php
namespace App\Http\Controllers\Api;

use App\Events\OrderCreated;
use App\Events\OrderUpdated;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function index()
    {
        return Order::latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.name' => ['required', 'string'],
            'items.*.size' => ['required', 'string'],
            'items.*.sugar' => ['required', 'string', 'max:30'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.image' => ['nullable', 'string'],
            'status' => ['sometimes', 'string'],
            'payment_method' => ['sometimes', 'in:cash,bakong'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        $total = collect($data['items'])->sum(fn ($i) => $i['qty'] * $i['price']);

        $paymentMethod = $data['payment_method'] ?? null;
        $isCash = $paymentMethod === 'cash';
        $isBakong = $paymentMethod === 'bakong';

        $paymentStatus = 'unpaid';
        if ($isCash) {
            $paymentStatus = 'paid';
        } elseif ($isBakong) {
            $paymentStatus = 'pending';
        }

        $orderStatus = $data['status'] ?? 'pending';
        if ($isCash) {
            $orderStatus = 'paid';
        }

        $order = Order::create([
            'reference' => 'ORD-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(4)),
            'status' => $orderStatus,
            'total' => $total,
            'items' => $data['items'],
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'payment_provider' => $isCash ? 'cash' : ($isBakong ? 'bakong' : null),
            'currency' => strtoupper($data['currency'] ?? 'USD'),
            'paid_at' => $isCash ? now() : null,
        ]);

        broadcast(new OrderCreated($order));
         Log::info('ORDER CREATED', ['id' => $order->id]);
    Log::info('BROADCAST DEFAULT', ['default' => config('broadcasting.default')]);

    event(new OrderCreated($order)); // ✅ do this (recommended)
    Log::info('ORDER EVENT FIRED', ['id' => $order->id]);

        return response()->json($order, 201);
    }
     public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,completed,cancelled'],
        ]);

        $order->update([
            'status' => $data['status'],
        ]);

        $order->refresh();

        // ✅ broadcast to realtime dashboard
        broadcast(new OrderUpdated($order))->toOthers();

        return response()->json($order, 200);
    }
}

