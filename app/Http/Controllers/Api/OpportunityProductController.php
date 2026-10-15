<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OpportunityProduct\StoreOpportunityProductRequest;
use App\Http\Requests\OpportunityProduct\UpdateOpportunityProductRequest;
use App\Http\Resources\Product\ProductResource;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Product;
use App\Services\OpportunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OpportunityProductController extends Controller
{
    public function __construct(private OpportunityService $opportunityService) {}

    public function index(Customer $customer, Opportunity $opportunity): AnonymousResourceCollection
    {
        $this->authorize('view', $opportunity);
        $this->ensureBelongsTo($customer, $opportunity);

        return ProductResource::collection($opportunity->products()->with(['category', 'tax'])->get());
    }

    public function store(StoreOpportunityProductRequest $request, Customer $customer, Opportunity $opportunity): JsonResponse
    {
        $this->authorize('update', $opportunity);
        $this->ensureBelongsTo($customer, $opportunity);

        $product = Product::findOrFail($request->validated('product_id'));
        $unitPrice = $request->validated('unit_price') ?? $product->unit_price;
        $quantity = $request->validated('quantity', 1);
        [$discountPercent, $discount] = $this->resolveDiscount(
            $request->validated('discount_percent'),
            (float) ($request->validated('discount_amount', 0) ?? 0),
            $quantity,
            (float) $unitPrice,
        );

        $opportunity->products()->syncWithoutDetaching([
            $product->id => [
                'quantity' => $quantity,
                // Custom price allowed — defaults to catalog price
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discount,
                'notes' => $request->validated('notes'),
            ],
        ]);

        $this->opportunityService->recalculateAmount($opportunity);

        return response()->json(['message' => 'Product attached to opportunity.'], JsonResponse::HTTP_CREATED);
    }

    public function update(UpdateOpportunityProductRequest $request, Customer $customer, Opportunity $opportunity, Product $product): JsonResponse
    {
        $this->authorize('update', $opportunity);
        $this->ensureBelongsTo($customer, $opportunity);

        $data = $request->validated();

        if (array_intersect(['quantity', 'unit_price', 'discount_amount', 'discount_percent'], array_keys($data))) {
            $pivot = $opportunity->products()->where('products.id', $product->id)->first()?->pivot;
            $quantity = $data['quantity'] ?? (int) ($pivot->quantity ?? 1);
            $unitPrice = (float) ($data['unit_price'] ?? $pivot->unit_price ?? $product->unit_price);

            if (array_key_exists('discount_percent', $data)) {
                // Percent key sent: it wins (null clears the rate and resets the amount).
                $percent = $data['discount_percent'];
                $amount = (float) ($data['discount_amount'] ?? ($percent === null ? 0 : $pivot->discount_amount ?? 0));
            } else {
                $percent = $pivot->discount_percent ?? null;
                $amount = (float) ($data['discount_amount'] ?? $pivot->discount_amount ?? 0);
            }

            [$data['discount_percent'], $data['discount_amount']] = $this->resolveDiscount($percent, $amount, $quantity, $unitPrice);
        }

        $opportunity->products()->updateExistingPivot($product->id, $data);

        $this->opportunityService->recalculateAmount($opportunity);

        return response()->json(['message' => 'Opportunity product updated.']);
    }

    public function destroy(Customer $customer, Opportunity $opportunity, Product $product): JsonResponse
    {
        $this->authorize('update', $opportunity);
        $this->ensureBelongsTo($customer, $opportunity);

        $opportunity->products()->detach($product->id);

        $this->opportunityService->recalculateAmount($opportunity);

        return response()->json(['message' => 'Product detached from opportunity.']);
    }

    public function sync(Customer $customer, Opportunity $opportunity): JsonResponse
    {
        $this->authorize('update', $opportunity);
        $this->ensureBelongsTo($customer, $opportunity);

        $data = request()->validate([
            'products' => ['required', 'array'],
            'products.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'products.*.quantity' => ['sometimes', 'integer', 'min:1'],
            'products.*.unit_price' => ['sometimes', 'numeric', 'min:0'],
            'products.*.discount_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'products.*.discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'products.*.notes' => ['nullable', 'string'],
        ]);

        $prices = Product::whereIn('id', collect($data['products'])->pluck('product_id'))->pluck('unit_price', 'id');

        $sync = collect($data['products'])->mapWithKeys(function ($item) use ($prices) {
            $quantity = $item['quantity'] ?? 1;
            $unitPrice = $item['unit_price'] ?? $prices[$item['product_id']] ?? 0;
            [$discountPercent, $discountAmount] = $this->resolveDiscount(
                $item['discount_percent'] ?? null,
                (float) ($item['discount_amount'] ?? 0),
                $quantity,
                (float) $unitPrice,
            );

            return [$item['product_id'] => [
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'notes' => $item['notes'] ?? null,
            ]];
        })->all();

        $opportunity->products()->sync($sync);

        $this->opportunityService->recalculateAmount($opportunity);

        return response()->json(['message' => 'Opportunity products synced.']);
    }

    private function ensureBelongsTo(Customer $customer, Opportunity $opportunity): void
    {
        if ($opportunity->customer_id !== $customer->id) {
            abort(404, 'Opportunity does not belong to this customer.');
        }
    }

    /**
     * Resolve a line discount. A percent wins over an amount when present;
     * otherwise the amount is used, capped at the line gross.
     *
     * @return array{0: ?float, 1: float} [percent, amount]
     */
    private function resolveDiscount(mixed $percent, float $amount, int $quantity, float $unitPrice): array
    {
        $gross = $quantity * $unitPrice;
        $percent = $percent === null || $percent === '' ? null : (float) $percent;

        if ($percent !== null && $percent > 0) {
            $percent = min($percent, 100);

            return [$percent, round($gross * ($percent / 100), 2)];
        }

        return [null, min(max($amount, 0), $gross)];
    }
}
