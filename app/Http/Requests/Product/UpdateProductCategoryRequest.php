<?php

namespace App\Http\Requests\Product;

use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update-products');
    }

    public function rules(): array
    {
        $category = $this->route('product_category');

        return [
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('product_categories', 'name')->ignore($category)],
            'parent_id' => ['sometimes', 'nullable', 'exists:product_categories,id', function ($attr, $val, $fail) use ($category) {
                if ($val && ($val == $category->id || in_array($val, ProductCategory::allDescendantIds($category->id)))) {
                    $fail('Cannot set parent to itself or a descendant.');
                }
            }],
            'description' => 'sometimes|nullable|string|max:500',
            'sort_order' => 'sometimes|integer|min:0',
        ];
    }
}
