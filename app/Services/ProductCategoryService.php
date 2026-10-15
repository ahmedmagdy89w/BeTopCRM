<?php

namespace App\Services;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ProductCategoryService
{
    public function tree(): Collection
    {
        return ProductCategory::roots()
            ->ordered()
            ->with(['children' => function ($q) {
                $q->ordered()->with('children');
            }])
            ->get();
    }

    public function list(): Collection
    {
        return ProductCategory::ordered()->get();
    }

    public function create(array $data): ProductCategory
    {
        return ProductCategory::create($data);
    }

    public function update(ProductCategory $cat, array $data): ProductCategory
    {
        $cat->update($data);

        return $cat->fresh();
    }

    public function delete(ProductCategory $cat): void
    {
        if ($cat->products()->active()->exists() || $cat->children()->exists()) {
            $labels = CompanySettingsService::productLabels(app()->getLocale(), __('reports.product'), __('reports.products'));
            throw ValidationException::withMessages([
                'category' => __('messages.product_category_delete_has_relations', ['products' => $labels['plural']]),
            ]);
        }

        $cat->products()->update(['category_id' => null]);
        $cat->delete();
    }

    public function reorder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            ProductCategory::where('id', $id)->update(['sort_order' => $index]);
        }
    }

    public function reorderWithSortOrder(array $categories): void
    {
        foreach ($categories as $item) {
            ProductCategory::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }
    }
}
