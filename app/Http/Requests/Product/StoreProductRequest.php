<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\CustomField\ValidatesCustomFields;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    use ValidatesCustomFields;

    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:50|unique:products,sku',
            'description' => 'nullable|string|max:2000',
            'unit_price' => 'required|numeric|min:0',
            'category_id' => 'nullable|exists:product_categories,id',
            'tax_id' => 'nullable|exists:taxes,id',
            'is_active' => 'boolean',
            'custom_fields' => ['nullable', 'array'],
            'category_custom_fields' => ['nullable', 'array'],
            ...$this->customFieldRules('product'),
            ...$this->categoryCustomFieldRules('product'),
        ];
    }

    protected function defaultCustomFieldEntityType(): ?string
    {
        return 'product';
    }
}
