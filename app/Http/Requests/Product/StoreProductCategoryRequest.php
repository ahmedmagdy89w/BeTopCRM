<?php

namespace App\Http\Requests\Product;

use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ProductCategory::class);
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:100|unique:product_categories,name',
            'parent_id'   => 'nullable|exists:product_categories,id',
            'description' => 'nullable|string|max:500',
            'sort_order'  => 'integer|min:0',
        ];
    }
}
