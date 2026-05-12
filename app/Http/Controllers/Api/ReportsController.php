<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\InventoryRecipe;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $end = now()->endOfDay();


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

        // all orders in the selected range
        $ordersQuery = Order::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end);

        $revenue = (float) $ordersQuery->clone()->sum('total');
        $ordersCount = (int) $ordersQuery->clone()->count();
        $avgOrder = $ordersCount ? ($revenue / $ordersCount) : 0.0;

        // DAILY (fill missing)
        $dailyRows = $ordersQuery->clone()
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

                // TOP ITEMS BY REVENUE (orders.items JSON -> cast to jsonb)
        $topItems = DB::select("
            SELECT
              (it.item->>'name') AS name,
                            SUM( (it.item->>'qty')::int ) AS qty,
                            SUM( ((it.item->>'qty')::numeric) * ((it.item->>'price')::numeric) ) AS amount
            FROM orders o
            CROSS JOIN LATERAL jsonb_array_elements(COALESCE(o.items, '[]'::json)::jsonb) AS it(item)
                        WHERE o.created_at BETWEEN ? AND ?
            GROUP BY (it.item->>'name')
                        ORDER BY amount DESC
            LIMIT 5
                ", [$start, $end]);

        $topItems = collect($topItems)->map(fn ($r) => [
            'name' => (string) $r->name,
            'qty' => (int) $r->qty,
                        'amount' => (float) $r->amount,
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
                        WHERE o.created_at BETWEEN ? AND ?
            GROUP BY COALESCE(pt.name, 'Other')
            ORDER BY total DESC
        ", [$start, $end]);

        $byType = collect($byType)->map(fn ($r) => [
            'type' => (string) $r->type,
            'total' => (float) $r->total,
        ])->values();

        $orders = $ordersQuery->clone()
            ->with([])
            ->latest()
            ->limit(100)
            ->get([
                'id',
                'reference',
                'table_no',
                'status',
                'total',
                'items',
                'created_at',
                'payment_method',
                'payment_provider',
                'payment_status',
                'paid_at',
            ]);

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
            'orders' => $orders,
        ]);
    }

    public function profit(Request $request)
    {
        $end = now()->endOfDay();
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
            if ($days > 90) {
                return response()->json([
                    'message' => 'Date range too large. Max 90 days.',
                ], 422);
            }

            $rangeKey = 'custom';
        } else {
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

        // Get all recipes for lookup by product_id|size
        $recipes = InventoryRecipe::all();
        $recipeMap = [];
        foreach ($recipes as $recipe) {
            $key = $recipe->product_id . '|' . $recipe->size;
            $recipeMap[$key] = $recipe;
        }

        // Get all orders in range
        $orders = Order::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->get(['id', 'total', 'items', 'created_at']);

        // Calculate total ingredient cost
        $totalSalesIncome = 0;
        $totalIngredientCost = 0;
        $ordersProcessed = 0;

        foreach ($orders as $order) {
            $totalSalesIncome += (float) $order->total;
            $ordersProcessed++;

            $items = $order->items ?? [];
            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $size = (string) ($item['size'] ?? '');
                $qty = (int) ($item['qty'] ?? 1);

                // Find matching recipe by product_id + size
                $key = $productId . '|' . $size;
                if (isset($recipeMap[$key])) {
                    $recipe = $recipeMap[$key];
                    $ingredientCostPerDrink = (float) $recipe->total_cost;
                    $totalIngredientCost += $ingredientCostPerDrink * $qty;
                }
            }
        }

        // Get waste cost from waste movements
        $wasteCost = (float) DB::selectOne("
            SELECT SUM(CAST(im.quantity AS FLOAT) * CAST(ii.cost_per_unit AS FLOAT)) as waste_cost
            FROM inventory_movements im
            JOIN inventory_items ii ON ii.id = im.inventory_item_id
            WHERE im.type = 'waste'
            AND im.created_at BETWEEN ? AND ?
        ", [$start, $end])?->waste_cost ?? 0;

        // Calculate profit
        $grossProfit = $totalSalesIncome - $totalIngredientCost;
        $finalProfit = $grossProfit - $wasteCost;

        // Daily profit breakdown
        $dailyRows = Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE(created_at) as day")
            ->selectRaw("SUM(total) as revenue")
            ->selectRaw("COUNT(*) as orders")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $dailyProfit = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $start->copy()->addDays($i);
            $key = $d->toDateString();
            $row = $dailyRows->get($key);

            // Get daily ingredient cost
            $dayOrders = Order::query()
                ->whereDate('created_at', $d)
                ->get(['total', 'items']);

            $dayIngredientCost = 0;
            foreach ($dayOrders as $order) {
                $items = $order->items ?? [];
                foreach ($items as $item) {
                    $productId = (int) ($item['product_id'] ?? 0);
                    $size = (string) ($item['size'] ?? '');
                    $qty = (int) ($item['qty'] ?? 1);

                    $itemKey = $productId . '|' . $size;
                    if (isset($recipeMap[$itemKey])) {
                        $recipe = $recipeMap[$itemKey];
                        $dayIngredientCost += ((float) $recipe->total_cost) * $qty;
                    }
                }
            }

            // Get daily waste cost
            $dayWasteCost = (float) DB::selectOne("
                SELECT SUM(CAST(im.quantity AS FLOAT) * CAST(ii.cost_per_unit AS FLOAT)) as waste_cost
                FROM inventory_movements im
                JOIN inventory_items ii ON ii.id = im.inventory_item_id
                WHERE im.type = 'waste'
                AND DATE(im.created_at) = ?
            ", [$d->toDateString()])?->waste_cost ?? 0;

            $dayRevenue = (float) ($row->revenue ?? 0);
            $dayGrossProfit = $dayRevenue - $dayIngredientCost;
            $dayNetProfit = $dayGrossProfit - $dayWasteCost;

            $dailyProfit[] = [
                'date' => $d->format('M d'),
                'sales_income' => round($dayRevenue, 2),
                'ingredient_cost' => round($dayIngredientCost, 2),
                'gross_profit' => round($dayGrossProfit, 2),
                'waste_cost' => round($dayWasteCost, 2),
                'final_profit' => round($dayNetProfit, 2),
            ];
        }

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
                'sales_income' => round($totalSalesIncome, 2),
                'ingredient_cost' => round($totalIngredientCost, 2),
                'gross_profit' => round($grossProfit, 2),
                'waste_cost' => round($wasteCost, 2),
                'final_profit' => round($finalProfit, 2),
                'orders' => $ordersProcessed,
            ],
            'daily' => $dailyProfit,
        ]);
    }
}
