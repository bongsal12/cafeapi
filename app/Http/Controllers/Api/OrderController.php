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
        // Return recent orders (last 200) to avoid huge payloads
        // Frontend can paginate or filter as needed
        return Order::query()
            ->latest()
            ->limit(200)
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'table_no' => ['nullable', 'string', 'max:100'],
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

        $paymentStatus = $isCash ? 'paid' : 'pending';
        $orderStatus = $isCash ? 'paid' : 'pending';

        $order = Order::create([
            'reference' => 'ORD-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(4)),
            'table_no' => $data['table_no'] ?? null,
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

    public function markAsPaid(Request $request, Order $order)
    {
        // Mark cash orders as paid by staff
        $order->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $order->refresh();

        broadcast(new OrderUpdated($order))->toOthers();

        return response()->json($order, 200);
    }
}

