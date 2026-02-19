<?php

namespace App\Http\Controllers;

use App\Models\ProductType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductTypeController extends Controller
{
    public function index()
    {
        return ProductType::query()->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:50'],
            'slug' => ['nullable','string','max:50','unique:product_types,slug'],
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $type = ProductType::create($data);

        return response()->json($type, 201);
    }

    public function show(ProductType $productType)
    {
        return $productType;
    }

    public function update(Request $request, ProductType $productType)
    {
        $data = $request->validate([
            'name' => ['sometimes','string','max:50'],
            'slug' => ['sometimes','string','max:50',"unique:product_types,slug,{$productType->id}"],
        ]);

        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $productType->update($data);

        return $productType;
    }

    public function destroy(ProductType $productType)
    {
        $productType->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
