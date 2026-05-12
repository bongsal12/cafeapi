<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryRecipe;
use App\Models\Order;
use App\Models\StockCountItem;
use App\Models\StockCountSession;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function availabilityForProduct(int $productId, ?string $size = null): array
    {
        // Keep products endpoint resilient during partial deploys before new migrations are run.
        if (!Schema::hasTable('inventory_recipes') || !Schema::hasTable('inventory_recipe_items')) {
            return [
                'available' => 999999,
                'reasons' => [],
                'recipe_id' => null,
            ];
        }

        $recipes = InventoryRecipe::query()
            ->with(['items.ingredient'])
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->when($size, fn ($q) => $q->where('size', $size))
            ->get();

        if ($recipes->isEmpty()) {
            return [
                'available' => 0,
                'reasons' => ['Missing recipe'],
                'recipe_id' => null,
            ];
        }

        $recipe = $recipes->first();
        $limits = [];
        $reasons = [];

        foreach ($recipe->items as $item) {
            $stock = (float) ($item->ingredient?->current_stock ?? 0);
            $required = max((float) $item->quantity_used, 0.000001);
            $possible = (int) floor($stock / $required);
            $limits[] = $possible;

            if ($possible === 0) {
                $reasons[] = "Not enough {$item->ingredient?->name}";
            }
        }

        return [
            'available' => $limits ? min($limits) : 0,
            'reasons' => $reasons,
            'recipe_id' => $recipe->id,
            'recipe' => $recipe,
        ];
    }

    public function availabilityForCart(Collection $items): array
    {
        $aggregate = [];
        $reasons = [];

        foreach ($items as $line) {
            $availability = $this->availabilityForProduct((int) $line['product_id'], $line['size'] ?? null);
            if (($availability['available'] ?? 0) <= 0) {
                $reasons[] = $line['name'] . ' is unavailable';
            }
        }

        foreach ($items as $line) {
            $recipe = InventoryRecipe::query()
                ->with('items')
                ->where('product_id', (int) $line['product_id'])
                ->where('size', $line['size'] ?? 'regular')
                ->where('is_active', true)
                ->first();

            if (! $recipe) {
                continue;
            }

            foreach ($recipe->items as $recipeItem) {
                $ingredientId = (int) $recipeItem->inventory_item_id;
                $aggregate[$ingredientId] = ($aggregate[$ingredientId] ?? 0) + ((float) $recipeItem->quantity_used * (int) $line['qty']);
            }

            // account for recipe sweetness options if present
            $sugarOpt = strtolower(trim((string) ($line['sugar'] ?? '')));
            if ($recipe->sweetness_options && is_array($recipe->sweetness_options)) {
                foreach ($recipe->sweetness_options as $opt) {
                    $level = strtolower(trim((string) ($opt['level'] ?? ($opt['name'] ?? ''))));
                    if ($level === $sugarOpt) {
                        $ingId = isset($opt['inventory_item_id']) ? (int) $opt['inventory_item_id'] : null;
                        $qty = isset($opt['quantity']) ? (float) $opt['quantity'] : 0.0;
                        if ($ingId) {
                            $aggregate[$ingId] = ($aggregate[$ingId] ?? 0) + ($qty * (int) $line['qty']);
                        }
                        break;
                    }
                }
            }
        }

        $missing = [];
        foreach ($aggregate as $ingredientId => $required) {
            $ingredient = InventoryItem::query()->find($ingredientId);
            $stock = (float) ($ingredient?->current_stock ?? 0);
            if ($stock < $required) {
                $missing[] = [
                    'ingredient' => $ingredient?->name ?? 'Unknown',
                    'required' => $required,
                    'available' => $stock,
                ];
            }
        }

        return [
            'ok' => empty($missing),
            'missing' => $missing,
            'aggregate' => $aggregate,
            'reasons' => $reasons,
        ];
    }

    public function deductOrderInventory(Order $order, ?User $actor = null): void
    {
        if ((bool) $order->inventory_deducted) {
            return;
        }

        $items = collect($order->items ?? []);
        if ($items->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($order, $items, $actor) {
            $requirements = [];

            foreach ($items as $line) {
                $recipe = InventoryRecipe::query()
                    ->with('items')
                    ->where('product_id', (int) ($line['product_id'] ?? 0))
                    ->where('size', (string) ($line['size'] ?? 'regular'))
                    ->where('is_active', true)
                    ->first();

                if (! $recipe) {
                    throw ValidationException::withMessages([
                        'items' => ["Missing recipe for {$line['name']} ({$line['size']})"],
                    ]);
                }

                foreach ($recipe->items as $recipeItem) {
                    $ingredientId = (int) $recipeItem->inventory_item_id;
                    $requirements[$ingredientId] = ($requirements[$ingredientId] ?? 0) + ((float) $recipeItem->quantity_used * (int) $line['qty']);
                }

                // account for recipe sweetness options if present
                $sugarOpt = strtolower(trim((string) ($line['sugar'] ?? '')));
                if ($recipe->sweetness_options && is_array($recipe->sweetness_options)) {
                    foreach ($recipe->sweetness_options as $opt) {
                        $level = strtolower(trim((string) ($opt['level'] ?? ($opt['name'] ?? ''))));
                        if ($level === $sugarOpt) {
                            $ingId = isset($opt['inventory_item_id']) ? (int) $opt['inventory_item_id'] : null;
                            $qty = isset($opt['quantity']) ? (float) $opt['quantity'] : 0.0;
                            if ($ingId) {
                                $requirements[$ingId] = ($requirements[$ingId] ?? 0) + ($qty * (int) $line['qty']);
                            }
                            break;
                        }
                    }
                }
            }

            $ingredients = InventoryItem::query()
                ->whereIn('id', array_keys($requirements))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($requirements as $ingredientId => $required) {
                $ingredient = $ingredients->get($ingredientId);
                $available = (float) ($ingredient?->current_stock ?? 0);
                if ($available < $required) {
                    throw ValidationException::withMessages([
                        'items' => ["Not enough {$ingredient?->name}. Need {$required}, available {$available}"],
                    ]);
                }
            }

            foreach ($requirements as $ingredientId => $required) {
                $ingredient = $ingredients->get($ingredientId);
                $before = (float) $ingredient->current_stock;
                $after = $before - $required;

                $ingredient->update(['current_stock' => $after]);

                InventoryMovement::query()->create([
                    'inventory_item_id' => $ingredient->id,
                    'type' => 'sale_usage',
                    'quantity' => $required,
                    'unit' => $ingredient->unit,
                    'before_stock' => $before,
                    'after_stock' => $after,
                    'note' => "Order #{$order->reference}",
                    'created_by' => $actor?->id,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                ]);
            }

            $order->update([
                'inventory_deducted' => true,
                'inventory_deducted_at' => now(),
            ]);
        });
    }

    public function recordMovement(array $data, string $type, ?User $actor = null, ?string $referenceType = null, ?int $referenceId = null): array
    {
        return DB::transaction(function () use ($data, $type, $actor, $referenceType, $referenceId) {
            $ingredient = InventoryItem::query()->lockForUpdate()->findOrFail((int) $data['inventory_item_id']);
            $before = (float) $ingredient->current_stock;
            $quantity = (float) $data['quantity'];

            if ($type === 'purchase' || $type === 'in') {
                $after = $before + $quantity;
            } elseif ($type === 'waste' || $type === 'sale_usage' || $type === 'out') {
                if ($before < $quantity) {
                    throw ValidationException::withMessages(['quantity' => ['Stock is not enough.']]);
                }
                $after = $before - $quantity;
            } elseif ($type === 'stock_count' || $type === 'adjustment') {
                $after = $quantity;
            } else {
                throw ValidationException::withMessages(['type' => ['Invalid movement type.']]);
            }

            $ingredient->update(['current_stock' => $after]);

            $movement = InventoryMovement::query()->create([
                'inventory_item_id' => $ingredient->id,
                'type' => $type,
                'quantity' => $quantity,
                'unit' => $ingredient->unit,
                'before_stock' => $before,
                'after_stock' => $after,
                'note' => $data['note'] ?? null,
                'created_by' => $actor?->id,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            return [$ingredient->fresh(), $movement];
        });
    }

    public function createStockCount(array $payload, ?User $actor = null): StockCountSession
    {
        return DB::transaction(function () use ($payload, $actor) {
            $session = StockCountSession::query()->create([
                'count_date' => $payload['count_date'] ?? now(),
                'branch' => $payload['branch'] ?? null,
                'counted_by' => $payload['counted_by'] ?? $actor?->id,
                'note' => $payload['note'] ?? null,
                'status' => $payload['status'] ?? 'draft',
            ]);

            foreach (($payload['items'] ?? []) as $row) {
                $ingredient = InventoryItem::query()->lockForUpdate()->findOrFail((int) $row['inventory_item_id']);
                $system = (float) $ingredient->current_stock;
                $actual = (float) $row['actual_count'];
                $difference = $actual - $system;
                $status = $difference === 0.0 ? 'balanced' : ($difference > 0 ? 'positive' : 'negative');

                $item = StockCountItem::query()->create([
                    'stock_count_session_id' => $session->id,
                    'inventory_item_id' => $ingredient->id,
                    'system_stock' => $system,
                    'actual_count' => $actual,
                    'difference' => $difference,
                    'reason' => $row['reason'] ?? null,
                    'status' => $status,
                ]);

                if (($payload['apply'] ?? false) && $difference !== 0.0) {
                    $after = $actual;
                    $ingredient->update(['current_stock' => $after]);
                    $movement = InventoryMovement::query()->create([
                        'inventory_item_id' => $ingredient->id,
                        'type' => 'stock_count',
                        'quantity' => abs($difference),
                        'unit' => $ingredient->unit,
                        'before_stock' => $system,
                        'after_stock' => $after,
                        'note' => $row['reason'] ?? 'Stock count adjustment',
                        'created_by' => $actor?->id,
                        'reference_type' => 'stock_count',
                        'reference_id' => $session->id,
                    ]);
                    $item->update(['movement_id' => $movement->id]);
                }
            }

            return $session->fresh(['items.ingredient', 'countedBy', 'approvedBy']);
        });
    }
}
