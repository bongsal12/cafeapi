<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;

class MenuController extends Controller
{
    public function index()
    {
        $categories = Category::with([
            'products' => function ($q) {
                $q->where('is_active', true)->with(['productType', 'variants']);
            }
        ])->get();

        $menu = [];

        foreach ($categories as $category) {
            foreach ($category->products as $product) {
                $type = $product->productType->slug;
                $key  = $product->slug;

                if ($product->variants->count() === 1) {
                    $v = $product->variants->first();

                    $menu[$category->slug][$type][$key] = [
                        'price' => (float) $v->price,
                        'size'  => $v->size,
                    ];
                } else {
                    foreach ($product->variants as $v) {
                        $menu[$category->slug][$type][$key]['sizes'][$v->size] = [
                            'price' => (float) $v->price,
                        ];
                    }
                }
            }
        }

        return response()->json([
            'menu' => $menu,
            'currency' => 'USD',
            'lastUpdated' => now()->toDateString(),
        ]);
    }
}
