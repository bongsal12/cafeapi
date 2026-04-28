<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;

class ProductController extends Controller
{
    // public function index()
    // {
    //     return Product::with(['category','productType','variants'])
    //         ->latest()
    //         ->paginate(20);
    // }
    public function index(Request $request)
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
    return $q->orderByDesc('id')->paginate(20);
}

    public function store(Request $request)
    {
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

    public function show(Product $product)
    {
        return $product->load(['category','productType','variants']);
    }

  public function update(Request $request, Product $product)
{
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
        $product->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
