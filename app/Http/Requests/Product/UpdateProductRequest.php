<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\CustomField\ValidatesCustomFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    use ValidatesCustomFields;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'name' => 'sometimes|string|max:255',
            'sku' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($product)],
            'description' => 'sometimes|nullable|string|max:2000',
            'unit_price' => 'sometimes|numeric|min:0',
            'category_id' => 'sometimes|nullable|exists:product_categories,id',
            'tax_id' => 'sometimes|nullable|exists:taxes,id',
            'is_active' => 'sometimes|boolean',
            'custom_fields' => ['sometimes', 'nullable', 'array'],
            'category_custom_fields' => ['sometimes', 'nullable', 'array'],
            ...$this->customFieldRules('product', true),
            ...$this->categoryCustomFieldRules('product', true),
        ];
    }

    protected function defaultCustomFieldEntityType(): ?string
    {
        return 'product';
    }
}
