<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $end = now()->endOfDay();

        // ✅ Custom range: from=YYYY-MM-DD&to=YYYY-MM-DD
        $from = $request->query('from');
        $to   = $request->query('to');

        $rangeKey = (string) $request->query('range', 'week');

        if ($from && $to) {
            try {
                $start = Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
                $end   = Carbon::createFromFormat('Y-m-d', $to)->endOfDay();
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'Invalid date format. Use from=YYYY-MM-DD&to=YYYY-MM-DD',
                ], 422);
            }

            if ($start->gt($end)) {
                return response()->json([
                    'message' => '`from` must be <= `to`',
                ], 422);
            }

            $days = $start->diffInDays($end->copy()->startOfDay()) + 1;

            // safety limit (optional)
            if ($days > 90) {
                return response()->json([
                    'message' => 'Date range too large. Max 90 days.',
                ], 422);
            }

            $rangeKey = 'custom';
        } else {
            // presets
            switch (strtolower($rangeKey)) {
                case 'day':
                    $start = now()->startOfDay();
                    $end = now()->endOfDay();
                    $days = 1;
                    break;

                case 'week':
                    $days = 7;
                    $start = now()->subDays($days - 1)->startOfDay();
                    $end = now()->endOfDay();
                    break;

                case 'month':
                    $start = now()->startOfMonth();
                    $end = now()->endOfDay();
                    $days = $start->diffInDays(now()->startOfDay()) + 1;
                    break;

                default:
                    // supports 7d, 14d, 30d...
                    $days = 7;
                    if (preg_match('/^(\d+)\s*d$/i', $rangeKey, $m)) {
                        $days = (int) $m[1];
                    }
                    $days = max(1, min(90, $days));
                    $start = now()->subDays($days - 1)->startOfDay();
                    $end = now()->endOfDay();
                    break;
            }
        }

        // paid orders only
        $paidOrders = Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('payment_status', 'paid');

        $revenue = (float) $paidOrders->clone()->sum('total');
        $ordersCount = (int) $paidOrders->clone()->count();
        $avgOrder = $ordersCount ? ($revenue / $ordersCount) : 0.0;

        // DAILY (fill missing)
        $dailyRows = $paidOrders->clone()
            ->selectRaw("DATE(created_at) as day")
            ->selectRaw("SUM(total) as total")
            ->selectRaw("COUNT(*) as orders")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $daily = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $start->copy()->addDays($i);
            $key = $d->toDateString();
            $row = $dailyRows->get($key);

            $daily[] = [
                'date' => $d->format('M d'),
                'total' => (float) ($row->total ?? 0),
                'orders' => (int) ($row->orders ?? 0),
            ];
        }

        // TOP ITEMS (orders.items JSON -> cast to jsonb)
        $topItems = DB::select("
            SELECT
              (it.item->>'name') AS name,
              SUM( (it.item->>'qty')::int ) AS qty
            FROM orders o
            CROSS JOIN LATERAL jsonb_array_elements(COALESCE(o.items, '[]'::json)::jsonb) AS it(item)
            WHERE o.payment_status = 'paid'
              AND o.created_at BETWEEN ? AND ?
            GROUP BY (it.item->>'name')
            ORDER BY qty DESC
            LIMIT 5
        ", [$start, $end]);

        $topItems = collect($topItems)->map(fn ($r) => [
            'name' => (string) $r->name,
            'qty' => (int) $r->qty,
        ])->values();

        // SALES BY TYPE
        $byType = DB::select("
            SELECT
              COALESCE(pt.name, 'Other') AS type,
              SUM(
                ((it.item->>'qty')::numeric) * ((it.item->>'price')::numeric)
              ) AS total
            FROM orders o
            CROSS JOIN LATERAL jsonb_array_elements(COALESCE(o.items, '[]'::json)::jsonb) AS it(item)
            LEFT JOIN products p
              ON p.id = NULLIF(it.item->>'product_id','')::int
            LEFT JOIN product_types pt
              ON pt.id = p.product_type_id
            WHERE o.payment_status = 'paid'
              AND o.created_at BETWEEN ? AND ?
            GROUP BY COALESCE(pt.name, 'Other')
            ORDER BY total DESC
        ", [$start, $end]);

        $byType = collect($byType)->map(fn ($r) => [
            'type' => (string) $r->type,
            'total' => (float) $r->total,
        ])->values();

        return response()->json([
            'range' => [
                'key' => $rangeKey,
                'days' => $days,
                'start' => $start->toISOString(),
                'end' => $end->toISOString(),
                'from' => $from,
                'to' => $to,
            ],
            'currency' => 'USD',
            'totals' => [
                'revenue' => round($revenue, 2),
                'orders' => $ordersCount,
                'avgOrder' => round($avgOrder, 2),
            ],
            'daily' => $daily,
            'topItems' => $topItems,
            'byType' => $byType,
        ]);
    }
}
