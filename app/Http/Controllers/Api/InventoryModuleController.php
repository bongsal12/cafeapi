<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryRecipe;
use App\Models\InventoryRecipeItem;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class InventoryModuleController extends Controller
{
    public function dashboard(InventoryService $service)
    {
        $ingredients = InventoryItem::query()->get();
        $movements = InventoryMovement::query()->latest()->limit(10)->get();

        $lowStock = $ingredients->where('current_stock', '<=', fn ($item) => $item->low_stock_alert)->count();
        $outOfStock = $ingredients->where('current_stock', '<=', 0)->count();

        $recentWaste = InventoryMovement::query()->where('type', 'waste')->whereDate('created_at', today())->get();
        $wasteCost = $recentWaste->sum(function ($movement) use ($ingredients) {
            $ingredient = $ingredients->firstWhere('id', $movement->inventory_item_id);
            return (float) ($ingredient?->cost_per_unit ?? 0) * (float) $movement->quantity;
        });

        $recipes = InventoryRecipe::query()->with(['items.ingredient', 'product'])->where('is_active', true)->get();
        $unavailable = collect();

        foreach ($recipes as $recipe) {
            $availability = $service->availabilityForProduct((int) $recipe->product_id, $recipe->size);
            if (($availability['available'] ?? 0) <= 0) {
                $unavailable->push([
                    'id' => $recipe->id,
                    'name' => $recipe->product?->name ?? 'Recipe',
                    'size' => $recipe->size,
                    'reason' => implode(', ', $availability['reasons'] ?? ['Unavailable']),
                ]);
            }
        }

        $topUsed = InventoryMovement::query()
            ->selectRaw('inventory_item_id, SUM(quantity) as total_qty')
            ->whereIn('type', ['sale_usage', 'waste', 'purchase'])
            ->whereDate('created_at', today())
            ->groupBy('inventory_item_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get()
            ->map(function ($row) use ($ingredients) {
                $ingredient = $ingredients->firstWhere('id', $row->inventory_item_id);
                return [
                    'name' => $ingredient?->name ?? 'Unknown',
                    'quantity' => (float) $row->total_qty,
                    'unit' => $ingredient?->unit ?? '',
                ];
            });

        return response()->json([
            'summary' => [
                'total_ingredients' => $ingredients->count(),
                'low_stock' => $lowStock,
                'out_of_stock' => $outOfStock,
                'today_waste_cost' => round($wasteCost, 2),
                'unavailable_menus' => $unavailable->count(),
            ],
            'low_stock_items' => $ingredients
                ->filter(fn ($item) => (float) $item->current_stock <= (float) $item->low_stock_alert)
                ->sortBy('name')
                ->take(6)
                ->values(),
            'recent_movements' => $movements,
            'unavailable_menu_items' => $unavailable->take(6)->values(),
            'top_used_ingredients' => $topUsed,
        ]);
    }

    public function stockIn(Request $request, InventoryService $service)
    {
        $data = $request->validate([
            'supplier' => ['required', 'string', 'max:120'],
            'invoice_no' => ['required', 'string', 'max:120'],
            'purchase_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $actor = $request->user();
        $rows = [];
        $grandTotal = 0;

        foreach ($data['items'] as $item) {
            [$ingredient, $movement] = $service->recordMovement([
                'inventory_item_id' => $item['inventory_item_id'],
                'quantity' => $item['quantity'],
                'note' => $data['note'] ?? 'Stock in',
            ], 'purchase', $actor, 'purchase', null);

            $ingredient->update(['cost_per_unit' => (float) $item['unit_cost']]);
            $lineTotal = (float) $item['quantity'] * (float) $item['unit_cost'];
            $grandTotal += $lineTotal;

            $rows[] = [
                'ingredient' => $ingredient->name,
                'unit' => $ingredient->unit,
                'quantity' => (float) $item['quantity'],
                'unit_cost' => (float) $item['unit_cost'],
                'total_cost' => round($lineTotal, 2),
                'movement_id' => $movement->id,
            ];
        }

        return response()->json([
            'supplier' => $data['supplier'],
            'invoice_no' => $data['invoice_no'],
            'purchase_date' => $data['purchase_date'],
            'note' => $data['note'] ?? null,
            'items' => $rows,
            'grand_total' => round($grandTotal, 2),
            'total_items' => count($rows),
            'total_quantity' => array_sum(array_column($rows, 'quantity')),
        ], 201);
    }

    public function waste(Request $request, InventoryService $service)
    {
        $data = $request->validate([
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'reason' => ['required', 'string', 'max:80'],
            'date' => ['sometimes', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $movementNote = 'Reason: ' . $data['reason'];
        if (!empty($data['note'])) {
            $movementNote .= ' | Note: ' . $data['note'];
        }

        [$ingredient, $movement] = $service->recordMovement([
            'inventory_item_id' => $data['inventory_item_id'],
            'quantity' => $data['quantity'],
            'note' => $movementNote,
        ], 'waste', $request->user(), 'waste', null);

        return response()->json([
            'ingredient' => $ingredient,
            'movement' => $movement,
            'reason' => $data['reason'],
            'note' => $data['note'] ?? null,
            'estimated_cost' => round((float) $ingredient->cost_per_unit * (float) $data['quantity'], 2),
        ], 201);
    }

    public function wasteRecords(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $limit = (int) ($data['limit'] ?? 300);

        $rows = InventoryMovement::query()
            ->with('inventoryItem:id,name')
            ->where('type', 'waste')
            ->when(!empty($data['from']), fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
            ->when(!empty($data['to']), fn ($q) => $q->whereDate('created_at', '<=', $data['to']))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (InventoryMovement $m) => [
                'id' => $m->id,
                'inventory_item_id' => $m->inventory_item_id,
                'ingredient_name' => $m->inventoryItem?->name,
                'type' => $m->type,
                'quantity' => (float) $m->quantity,
                'unit' => $m->unit,
                'before_stock' => (float) $m->before_stock,
                'after_stock' => (float) $m->after_stock,
                'note' => $m->note,
                'created_at' => optional($m->created_at)->toISOString(),
            ])
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function stockInRecords(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $limit = (int) ($data['limit'] ?? 300);

        $rows = InventoryMovement::query()
            ->with('inventoryItem:id,name')
            ->where('type', 'purchase')
            ->when(!empty($data['from']), fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
            ->when(!empty($data['to']), fn ($q) => $q->whereDate('created_at', '<=', $data['to']))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (InventoryMovement $m) => [
                'id' => $m->id,
                'inventory_item_id' => $m->inventory_item_id,
                'ingredient_name' => $m->inventoryItem?->name,
                'type' => $m->type,
                'quantity' => (float) $m->quantity,
                'unit' => $m->unit,
                'before_stock' => (float) $m->before_stock,
                'after_stock' => (float) $m->after_stock,
                'note' => $m->note,
                'created_at' => optional($m->created_at)->toISOString(),
            ])
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function movementRecords(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $limit = (int) ($data['limit'] ?? 300);

        $rows = InventoryMovement::query()
            ->with('inventoryItem:id,name')
            ->when(!empty($data['from']), fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
            ->when(!empty($data['to']), fn ($q) => $q->whereDate('created_at', '<=', $data['to']))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (InventoryMovement $m) => [
                'id' => $m->id,
                'inventory_item_id' => $m->inventory_item_id,
                'ingredient_name' => $m->inventoryItem?->name,
                'type' => $m->type,
                'quantity' => (float) $m->quantity,
                'unit' => $m->unit,
                'before_stock' => (float) $m->before_stock,
                'after_stock' => (float) $m->after_stock,
                'note' => $m->note,
                'created_at' => optional($m->created_at)->toISOString(),
            ])
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function recipes(Request $request)
    {
        $recipes = InventoryRecipe::query()
            ->with(['product.category', 'items.ingredient'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = trim((string) $request->search);
                $q->whereHas('product', fn ($qq) => $qq->where('name', 'ILIKE', "%{$s}%"));
            })
            ->orderByDesc('id')
            ->get()
            ->map(fn (InventoryRecipe $recipe) => $this->formatRecipe($recipe))
            ->values();

        return response()->json(['data' => $recipes]);
    }

    public function storeRecipe(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'size' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'sweetness_options' => ['sometimes', 'nullable', 'array'],
            'sweetness_options.*.level' => ['required_with:sweetness_options', 'string', 'max:120'],
            'sweetness_options.*.inventory_item_id' => ['required_with:sweetness_options', 'integer', 'exists:inventory_items,id'],
            'sweetness_options.*.quantity' => ['required_with:sweetness_options', 'numeric', 'min:0'],
            'items.*.inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'items.*.quantity_used' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit' => ['required', 'string', 'max:30'],
        ]);

        $recipe = InventoryRecipe::query()->updateOrCreate(
            ['product_id' => $data['product_id'], 'size' => $data['size']],
            [
                'description' => $data['description'] ?? null,
                'selling_price' => $data['selling_price'],
                'is_active' => $data['is_active'] ?? true,
                'total_cost' => 0,
                'sweetness_options' => $data['sweetness_options'] ?? null,
            ]
        );

        $recipe->items()->delete();

        $totalCost = 0;
        foreach ($data['items'] as $row) {
            $ingredient = InventoryItem::query()->findOrFail($row['inventory_item_id']);
            $totalCost += ((float) $row['quantity_used']) * ((float) $ingredient->cost_per_unit);
            InventoryRecipeItem::query()->create([
                'inventory_recipe_id' => $recipe->id,
                'inventory_item_id' => $row['inventory_item_id'],
                'quantity_used' => $row['quantity_used'],
                'unit' => $row['unit'],
            ]);
        }

        $recipe->update(['total_cost' => $totalCost]);

        return response()->json($this->formatRecipe($recipe->fresh(['product.category', 'items.ingredient'])), 201);
    }

    public function showRecipe(InventoryRecipe $recipe, InventoryService $service)
    {
        $recipe->load(['product.category', 'items.ingredient']);
        $availability = $service->availabilityForProduct((int) $recipe->product_id, $recipe->size);

        return response()->json([
            'recipe' => $this->formatRecipe($recipe),
            'availability' => $availability,
        ]);
    }

    public function updateRecipe(Request $request, InventoryRecipe $recipe)
    {
        $data = $request->validate([
            'size' => ['sometimes', 'required', 'string', 'max:50'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'selling_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array', 'min:1'],
            'sweetness_options' => ['sometimes', 'nullable', 'array'],
            'sweetness_options.*.level' => ['required_with:sweetness_options', 'string', 'max:120'],
            'sweetness_options.*.inventory_item_id' => ['required_with:sweetness_options', 'integer', 'exists:inventory_items,id'],
            'sweetness_options.*.quantity' => ['required_with:sweetness_options', 'numeric', 'min:0'],
        ]);

        $recipe->update(collect($data)->except('items')->toArray());
        if (isset($data['items'])) {
            $recipe->items()->delete();
            foreach ($data['items'] as $row) {
                InventoryRecipeItem::query()->create([
                    'inventory_recipe_id' => $recipe->id,
                    'inventory_item_id' => $row['inventory_item_id'],
                    'quantity_used' => $row['quantity_used'],
                    'unit' => $row['unit'],
                ]);
            }
        }

        if (array_key_exists('sweetness_options', $data)) {
            $recipe->update(['sweetness_options' => $data['sweetness_options'] ?? null]);
        }

        return response()->json($this->formatRecipe($recipe->fresh(['product.category', 'items.ingredient'])));
    }

    public function destroyRecipe(InventoryRecipe $recipe)
    {
        $recipe->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function stockCounts(Request $request, InventoryService $service)
    {
        $data = $request->validate([
            'count_date' => ['required', 'date'],
            'branch' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'in:draft,in progress,submitted,approved'],
            'apply' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'items.*.actual_count' => ['required', 'numeric', 'min:0'],
            'items.*.reason' => ['nullable', 'string', 'max:80'],
        ]);

        $session = $service->createStockCount([
            'count_date' => $data['count_date'],
            'branch' => $data['branch'] ?? null,
            'note' => $data['note'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'apply' => $data['apply'] ?? false,
            'items' => $data['items'],
            'counted_by' => $request->user()?->id,
        ], $request->user());

        return response()->json($session, 201);
    }

    public function availability(Request $request, InventoryService $service)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'size' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json($service->availabilityForProduct((int) $data['product_id'], $data['size'] ?? null));
    }

    private function formatRecipe(InventoryRecipe $recipe): array
    {
        $recipe->loadMissing(['product.category', 'items.ingredient']);

        $items = $recipe->items->map(function (InventoryRecipeItem $item) {
            $ingredient = $item->ingredient;
            $costPerDrink = round(((float) $ingredient?->cost_per_unit ?? 0) * (float) $item->quantity_used, 4);

            return [
                'id' => $item->id,
                'inventory_item_id' => $item->inventory_item_id,
                'ingredient' => $ingredient?->name,
                'quantity_used' => (float) $item->quantity_used,
                'unit' => $item->unit,
                'cost_per_unit' => (float) ($ingredient?->cost_per_unit ?? 0),
                'cost_per_drink' => $costPerDrink,
            ];
        })->values();

        $sweetness = array_values(array_map(fn($s) => [
            'level' => $s['level'] ?? ($s['name'] ?? ''),
            'inventory_item_id' => isset($s['inventory_item_id']) ? (int) $s['inventory_item_id'] : null,
            'quantity' => isset($s['quantity']) ? (float) $s['quantity'] : 0,
        ], (array) ($recipe->sweetness_options ?? [])));

        $selling = (float) $recipe->selling_price;
        $cost = (float) $recipe->total_cost;
        $profit = $selling - $cost;

        return [
            'id' => $recipe->id,
            'product_id' => $recipe->product_id,
            'product_name' => $recipe->product?->name,
            'category' => $recipe->product?->category?->name,
            'size' => $recipe->size,
            'description' => $recipe->description,
            'selling_price' => $selling,
            'total_cost' => round($cost, 4),
            'profit' => round($profit, 4),
            'margin' => $selling > 0 ? round(($profit / $selling) * 100, 2) : 0,
            'status' => $recipe->is_active ? 'Active' : 'Inactive',
            'items' => $items,
            'sweetness_options' => $sweetness,
        ];
    }
}
