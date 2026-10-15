<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\Opportunity\OpportunityResource;
use App\Http\Resources\Product\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function __construct(private ProductService $productService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);
        $products = $this->productService->list($request->query());

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create($request->validated(), $request->user());

        return (new ProductResource($product->load(['category', 'tax'])))->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        $this->authorize('view', $product);

        return new ProductResource($this->productService->find($product->id));
    }

    public function opportunities(Product $product): AnonymousResourceCollection
    {
        $this->authorize('view', $product);

        return OpportunityResource::collection($this->productService->listOpportunities($product));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product = $this->productService->update($product, $request->validated());

        return new ProductResource($product);
    }

    public function destroy(Product $product): Response
    {
        $this->authorize('delete', $product);
        $this->productService->delete($product);

        return response()->noContent();
    }

    public function toggleActive(Product $product): ProductResource
    {
        $this->authorize('update', $product);
        $product->update(['is_active' => ! $product->is_active]);

        return new ProductResource($product->fresh(['category', 'tax']));
    }

    public function byCategory(Request $request, ProductCategory $productCategory): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);
        $products = Product::with(['category', 'tax'])
            ->inCategory($productCategory->id)
            ->active()
            ->orderBy('name')
            ->paginate(min((int) ($request->input('per_page', 15)), 100));

        return ProductResource::collection($products);
    }
}
