<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductCategoryRequest;
use App\Http\Requests\Product\UpdateProductCategoryRequest;
use App\Http\Resources\Product\ProductCategoryResource;
use App\Models\ProductCategory;
use App\Services\ProductCategoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductCategoryController extends Controller
{
    public function __construct(private ProductCategoryService $categoryService) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ProductCategory::class);
        return ProductCategoryResource::collection($this->categoryService->list());
    }

    public function tree(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ProductCategory::class);
        return ProductCategoryResource::collection($this->categoryService->tree());
    }

    public function store(StoreProductCategoryRequest $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $cat = $this->categoryService->create($data);
        return (new ProductCategoryResource($cat))->response()->setStatusCode(201);
    }

    public function show(ProductCategory $productCategory): ProductCategoryResource
    {
        $this->authorize('view', $productCategory);
        $productCategory->load(['children' => function ($q) {
            $q->ordered()->with('children');
        }]);
        return new ProductCategoryResource($productCategory);
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory): ProductCategoryResource
    {
        $this->authorize('update', $productCategory);
        $cat = $this->categoryService->update($productCategory, $request->validated());
        return new ProductCategoryResource($cat);
    }

    public function destroy(ProductCategory $productCategory): Response
    {
        $this->authorize('delete', $productCategory);
        $this->categoryService->delete($productCategory);
        return response()->noContent();
    }

    public function reorder(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('create', ProductCategory::class);
        $request->validate([
            'categories'             => 'required|array',
            'categories.*.id'        => 'required|integer|exists:product_categories,id',
            'categories.*.sort_order' => 'required|integer|min:0',
        ]);
        $this->categoryService->reorderWithSortOrder($request->input('categories'));
        return response()->json(['message' => 'Reordered.']);
    }
}
