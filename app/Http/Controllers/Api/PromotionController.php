<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Promotion::with(['product', 'category'])->orderByDesc('id')->get()->map(fn (Promotion $promo) => $this->mapPromotion($promo))->values();
        return response()->json(['data' => $items]);
    }

    public function show(Promotion $promotion): JsonResponse
    {
        return response()->json($this->mapPromotion($promotion->load(['product', 'category'])));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:150'],
            'scope_type' => ['required', Rule::in(['product', 'category'])],
            'product_id' => ['nullable','required_if:scope_type,product','exists:products,id'],
            'category_id' => ['nullable','required_if:scope_type,category','exists:categories,id'],
            'percent' => ['required','integer','min:1','max:100'],
            'apply_to_variants' => ['sometimes','boolean'],
            'start_at' => ['nullable','date'],
            'end_at' => ['nullable','date','after_or_equal:start_at'],
            'active' => ['sometimes','boolean'],
        ]);

        $promo = Promotion::create($data);
        return response()->json($this->mapPromotion($promo->load(['product', 'category'])), 201);
    }

    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes','string','max:150'],
            'scope_type' => ['sometimes', Rule::in(['product', 'category'])],
            'product_id' => ['nullable','sometimes','required_if:scope_type,product','exists:products,id'],
            'category_id' => ['nullable','sometimes','required_if:scope_type,category','exists:categories,id'],
            'percent' => ['sometimes','integer','min:1','max:100'],
            'apply_to_variants' => ['sometimes','boolean'],
            'start_at' => ['nullable','date'],
            'end_at' => ['nullable','date','after_or_equal:start_at'],
            'active' => ['sometimes','boolean'],
        ]);

        $promotion->update($data);

        return response()->json($this->mapPromotion($promotion->fresh()->load(['product', 'category'])));
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        $promotion->delete();
        return response()->json(['message' => 'Deleted']);
    }

    private function mapPromotion(Promotion $promo): array
    {
        return [
            'id' => $promo->id,
            'name' => $promo->name,
            'scope_type' => $promo->scope_type ?? 'product',
            'product_id' => $promo->product_id,
            'category_id' => $promo->category_id,
            'percent' => (int) $promo->percent,
            'apply_to_variants' => (bool) $promo->apply_to_variants,
            'start_at' => optional($promo->start_at)?->toDateTimeString(),
            'end_at' => optional($promo->end_at)?->toDateTimeString(),
            'active' => (bool) $promo->active,
            'status' => $promo->status,
            'scope_label' => $promo->scope_label,
            'product' => $promo->product,
            'category' => $promo->category,
            'created_at' => optional($promo->created_at)?->toDateTimeString(),
            'updated_at' => optional($promo->updated_at)?->toDateTimeString(),
        ];
    }
}
