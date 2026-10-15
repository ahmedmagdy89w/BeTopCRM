<?php

namespace App\Http\Requests\Tax;

use App\Models\Tax;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('tax'));
    }

    public function rules(): array
    {
        /** @var Tax $tax */
        $tax = $this->route('tax');

        return [
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('taxes', 'name')->ignore($tax->id)],
            'percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
