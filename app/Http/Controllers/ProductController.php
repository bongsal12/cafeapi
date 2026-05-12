<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\InventoryService;

class ProductController extends Controller
{
    // public function index()
    // {
    //     return Product::with(['category','productType','variants'])
    //         ->latest()
    //         ->paginate(20);
    // }
    public function index(Request $request, InventoryService $inventory)
{
    $q = Product::query()
        ->with(['variants', 'category', 'productType']);

    // ✅ filter by category
    if ($request->filled('category_id')) {
        $q->where('category_id', (int) $request->category_id);
    }

    // ✅ filter by product type
    if ($request->filled('product_type_id')) {
        $q->where('product_type_id', (int) $request->product_type_id);
    }

    // ✅ search by name/slug
    if ($request->filled('search')) {
        $s = trim((string) $request->search);
        $q->where(function ($qq) use ($s) {
            $qq->where('name', 'ILIKE', "%{$s}%")
               ->orWhere('slug', 'ILIKE', "%{$s}%");
        });
    }

    if ($request->has('is_active')) {
        $q->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
    }

    // pagination (keep your existing style)
    $paginated = $q->orderByDesc('id')->paginate(20);

    $this->attachActivePromotions($paginated->getCollection());
    $this->attachInventoryAvailability($paginated->getCollection(), $inventory);

    return $paginated;
}

    public function store(Request $request)
    {
        // Frontend sends variants as JSON string in multipart/form-data.
        if (is_string($request->input('variants'))) {
            $decoded = json_decode($request->input('variants'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request->merge(['variants' => $decoded]);
            }
        }

        $data = $request->validate([
            'category_id'     => ['required','exists:categories,id'],
            'product_type_id' => ['required','exists:product_types,id'],
            'name'            => ['required','string','max:120'],
            'slug'            => ['nullable','string','max:120','unique:products,slug'],
            'image'           => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:2048'],
            'is_active'       => ['sometimes', 'boolean'],


            'variants'              => ['required','array','min:1'],
            'variants.*.size'       => ['required','string','max:50'],
            'variants.*.price'      => ['required','numeric','min:0'],
        ]);

      if ($request->hasFile('image')) {
        $path = $request->file('image')->store('products', 'public');
        $data['image'] = $path;
    } else {

    }

    $variants = $data['variants'];
    unset($data['variants']);

    $data['slug'] = $data['slug'] ?? Str::slug($data['name'], '_');
    $data['is_active'] = $data['is_active'] ?? true;


    $product = DB::transaction(function () use ($data, $variants) {
        $product = Product::create($data);
        $product->variants()->createMany($variants);

        return $product;
    });


        return response()->json($product->load(['category','productType','variants']), 201);
    }

    public function show(Product $product, InventoryService $inventory)
    {
        $loaded = $product->load(['category','productType','variants']);
        $this->attachActivePromotions(collect([$loaded]));
        $this->attachInventoryAvailability(collect([$loaded]), $inventory);
        return $loaded;
    }

  public function update(Request $request, Product $product)
{
    // Frontend sends variants as JSON string in multipart/form-data.
    if (is_string($request->input('variants'))) {
        $decoded = json_decode($request->input('variants'), true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $request->merge(['variants' => $decoded]);
        }
    }

    $data = $request->validate([
        'category_id'     => ['sometimes', 'required', 'exists:categories,id'],
        'product_type_id' => ['sometimes', 'required', 'exists:product_types,id'],
        'name'            => ['sometimes', 'required', 'string', 'max:120'],
        'slug'            => ['sometimes', 'required', 'string', 'max:120', "unique:products,slug,{$product->id}"],

        // Allow file upload OR string (URL/path). 'sometimes' means only validate if present.
        'image'           => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:2048'],
        'is_active'       => ['sometimes', 'boolean'],

        'variants'              => ['sometimes', 'array', 'min:1'],
        'variants.*.size'       => ['required_with:variants', 'string', 'max:50'],
        'variants.*.price'      => ['required_with:variants', 'numeric', 'min:0'],
    ]);

    if ($request->hasFile('image')) {
        if ($product->image) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($product->image);
        }
        $data['image'] = $request->file('image')->store('products', 'public');
    } elseif ($request->has('image') && $data['image'] === null) {

        if ($product->image) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($product->image);
        }
        $data['image'] = null;
    }
    if ($request->has('name') && ! $request->has('slug')) {
        $data['slug'] = Str::slug($request->name, '_');
        $data['slug'] = $data['slug'] . ($product->slug === $data['slug'] ? '' : '_' . $product->id);
    }

    $updatedProduct = DB::transaction(function () use ($product, $data) {
        $product->update(collect($data)->except('variants')->toArray());

        if (isset($data['variants'])) {
            $product->variants()->delete();
            $product->variants()->createMany($data['variants']);
        }

        return $product->fresh();
    });

    return response()->json(
        $updatedProduct->load(['category', 'productType', 'variants']),
        200
    );
}

    public function destroy(Product $product)
    {
        Product::query()->whereKey($product->id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    private function attachActivePromotions(Collection $products): void
    {
        if ($products->isEmpty()) {
            return;
        }

        $now = now();
        $productIds = $products->pluck('id')->filter()->unique()->values();
        $categoryIds = $products->pluck('category_id')->filter()->unique()->values();

        $promotions = Promotion::query()
            ->where('active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', $now);
            })
            ->where(function ($q) use ($productIds, $categoryIds) {
                $q->where(function ($qq) use ($productIds) {
                    $qq->where(function ($scoped) {
                        $scoped->where('scope_type', 'product')->orWhereNull('scope_type');
                    })->whereIn('product_id', $productIds);
                })->orWhere(function ($qq) use ($categoryIds) {
                    $qq->where('scope_type', 'category')->whereIn('category_id', $categoryIds);
                });
            })
            ->get();

        foreach ($products as $product) {
            $best = $promotions
                ->filter(function (Promotion $promo) use ($product) {
                    $scope = $promo->scope_type ?: 'product';
                    if ($scope === 'category') {
                        return (int) $promo->category_id === (int) $product->category_id;
                    }
                    return (int) $promo->product_id === (int) $product->id;
                })
                ->sortByDesc('percent')
                ->first();

            $product->setAttribute('active_promotion', $best ? [
                'id' => $best->id,
                'name' => $best->name,
                'scope_type' => $best->scope_type ?: 'product',
                'percent' => (int) $best->percent,
                'start_at' => optional($best->start_at)?->toDateTimeString(),
                'end_at' => optional($best->end_at)?->toDateTimeString(),
            ] : null);

            $hasDiscount = false;

            foreach ($product->variants as $variant) {
                $original = round((float) $variant->price, 2);
                $discounted = $original;

                if ($best) {
                    $discounted = round(max(0, $original * (1 - ((int) $best->percent / 100))), 2);
                }

                if ($discounted < $original) {
                    $hasDiscount = true;
                }

                $variant->setAttribute('original_price', $original);
                $variant->setAttribute('discounted_price', $discounted);
                $variant->setAttribute('has_discount', $discounted < $original);
            }

            $product->setAttribute('has_discount', $hasDiscount);
        }
    }

    private function attachInventoryAvailability(Collection $products, InventoryService $inventory): void
    {
        foreach ($products as $product) {
            $primaryVariant = $product->variants->first();
            $size = $primaryVariant->size ?? 'regular';
            $availability = $inventory->availabilityForProduct((int) $product->id, $size);

            $product->setAttribute('inventory_availability', [
                'available' => (int) ($availability['available'] ?? 0),
                'reasons' => $availability['reasons'] ?? [],
                'size' => $size,
            ]);
            $product->setAttribute('is_sold_out', (int) ($availability['available'] ?? 0) <= 0);
        }
    }
}
