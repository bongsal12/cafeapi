<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $q = InventoryItem::query()
            ->select([
                'id',
                'name',
                'category',
                'slug',
                'image',
                'current_stock',
                'unit',
                'low_stock_alert',
                'cost_per_unit',
                'sweetness_levels',
                'is_active',
                'updated_at',
            ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('slug', 'ILIKE', "%{$search}%");
            });
        }

        if ($request->boolean('low_only')) {
            $q->whereColumn('current_stock', '<=', 'low_stock_alert');
        }

        $items = $q->orderBy('name')->get()->map(fn (InventoryItem $p) => $this->mapItem($p))->values();

        return response()->json(['data' => $items]);
    }

    public function updateSettings(Request $request, InventoryItem $item)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'category' => ['sometimes', 'required', 'string', 'max:80'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:120', 'unique:inventory_items,slug,'.$item->id],
            'unit' => ['sometimes', 'required', 'string', 'max:30'],
            'low_stock_alert' => ['sometimes', 'required', 'numeric', 'min:0'],
            'cost_per_unit' => ['sometimes', 'required', 'numeric', 'min:0'],
            'sweetness_levels' => ['sometimes', 'nullable', 'array'],
            'sweetness_levels.*' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['sometimes','nullable','file','image','mimes:jpeg,png,jpg,gif,svg,webp','max:2048'],
        ]);

        if (array_key_exists('sweetness_levels', $data)) {
            $data['sweetness_levels'] = array_values(array_filter(array_map(
                fn ($level) => trim((string) $level),
                $data['sweetness_levels'] ?? []
            )));
        }

        if ($request->hasFile('image')) {
            if ($item->image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($item->image);
            }
            $data['image'] = $request->file('image')->store('inventory', 'public');
        }

        $item->update($data);

        return response()->json($this->mapItem($item->fresh()));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'category' => ['sometimes','required','string','max:80'],
            'slug' => ['nullable','string','max:120','unique:inventory_items,slug'],
            'image' => ['sometimes','nullable','file','image','mimes:jpeg,png,jpg,gif,svg,webp','max:2048'],
            'current_stock' => ['sometimes','numeric','min:0'],
            'unit' => ['sometimes','string','max:30'],
            'low_stock_alert' => ['sometimes','numeric','min:0'],
            'cost_per_unit' => ['sometimes','numeric','min:0'],
            'sweetness_levels' => ['sometimes', 'nullable', 'array'],
            'sweetness_levels.*' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes','boolean'],
        ]);

        if (array_key_exists('sweetness_levels', $data)) {
            $data['sweetness_levels'] = array_values(array_filter(array_map(
                fn ($level) => trim((string) $level),
                $data['sweetness_levels'] ?? []
            )));
        }

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('inventory', 'public');
        }

        $item = InventoryItem::query()->create([
            'name' => $data['name'],
            'category' => $data['category'] ?? 'Other',
            'slug' => $data['slug'] ?? null,
            'image' => $data['image'] ?? null,
            'current_stock' => $data['current_stock'] ?? 0,
            'unit' => $data['unit'] ?? 'unit',
            'low_stock_alert' => $data['low_stock_alert'] ?? 0,
            'cost_per_unit' => $data['cost_per_unit'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        // if initial stock provided, record a movement
        if (isset($data['current_stock']) && (float)$data['current_stock'] > 0) {
            InventoryMovement::query()->create([
                'inventory_item_id' => $item->id,
                'type' => 'in',
                'quantity' => (float)$data['current_stock'],
                'unit' => (string)$item->unit,
                'before_stock' => 0.0,
                'after_stock' => (float)$data['current_stock'],
                'note' => 'initial stock',
                'created_by' => optional($request->user())->id,
            ]);
        }

        return response()->json($this->mapItem($item->fresh()), 201);
    }

    public function destroy(InventoryItem $item)
    {
        // Do not delete the physical image file here.
        // Inventory items and products are separate records, but they may
        // still point to files on the same public storage disk.
        // Removing the file could break unrelated product images.
        // delete movements
        InventoryMovement::query()->where('inventory_item_id', $item->id)->delete();
        $item->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function moveStock(Request $request)
    {
        $data = $request->validate([
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'type' => ['required', 'in:in,out,adjustment,purchase,waste,sale_usage,stock_count'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        [$updatedItem, $movement] = DB::transaction(function () use ($data, $request) {
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($data['inventory_item_id']);

            $before = (float) $item->current_stock;
            $qty = (float) $data['quantity'];
            $type = (string) $data['type'];

            if ($type === 'in') {
                $after = $before + $qty;
            } elseif ($type === 'out') {
                $after = $before - $qty;
                if ($after < 0) {
                    throw ValidationException::withMessages([
                        'quantity' => ['Stock out exceeds current stock.'],
                    ]);
                }
            } else {
                // adjustment: quantity is the absolute new stock value
                $after = $qty;
            }

            $item->current_stock = $after;
            $item->save();

            $movement = InventoryMovement::query()->create([
                'inventory_item_id' => $item->id,
                'type' => $type,
                'quantity' => $qty,
                'unit' => (string) $item->unit,
                'before_stock' => $before,
                'after_stock' => $after,
                'note' => $data['note'] ?? null,
                'created_by' => optional($request->user())->id,
            ]);

            return [$item->fresh(), $movement->load('creator:id,name')];
        });

        return response()->json([
            'item' => $this->mapItem($updatedItem),
            'movement' => [
                'id' => $movement->id,
                'type' => $movement->type,
                'quantity' => (float) $movement->quantity,
                'unit' => $movement->unit,
                'before_stock' => (float) $movement->before_stock,
                'after_stock' => (float) $movement->after_stock,
                'note' => $movement->note,
                'created_by' => $movement->created_by,
                'created_by_name' => optional($movement->creator)->name,
                'created_at' => optional($movement->created_at)->toISOString(),
            ],
        ]);
    }

    public function movements(InventoryItem $item, Request $request)
    {
        $limit = (int) $request->query('limit', 30);
        $limit = max(1, min(200, $limit));

        $rows = InventoryMovement::query()
            ->with('creator:id,name')
            ->where('inventory_item_id', $item->id)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (InventoryMovement $m) => [
                'id' => $m->id,
                'type' => $m->type,
                'quantity' => (float) $m->quantity,
                'unit' => $m->unit,
                'before_stock' => (float) $m->before_stock,
                'after_stock' => (float) $m->after_stock,
                'note' => $m->note,
                'created_by' => $m->created_by,
                'created_by_name' => optional($m->creator)->name,
                'created_at' => optional($m->created_at)->toISOString(),
            ])
            ->values();

        return response()->json(['data' => $rows]);
    }

    private function mapItem(InventoryItem $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'category' => $p->category,
            'slug' => $p->slug,
            'image' => $p->image,
            'current_stock' => (float) $p->current_stock,
            'unit' => (string) $p->unit,
            'low_stock_alert' => (float) $p->low_stock_alert,
            'cost_per_unit' => (float) ($p->cost_per_unit ?? 0),
            'sweetness_levels' => array_values(array_filter(array_map(
                fn ($level) => trim((string) $level),
                (array) ($p->sweetness_levels ?? [])
            ))),
            'is_low_stock' => (float) $p->current_stock <= (float) $p->low_stock_alert,
            'is_active' => (bool) $p->is_active,
            'updated_at' => optional($p->updated_at)->toISOString(),
        ];
    }
}
