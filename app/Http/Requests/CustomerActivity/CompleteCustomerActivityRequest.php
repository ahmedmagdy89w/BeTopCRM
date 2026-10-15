<?php

namespace App\Http\Requests\CustomerActivity;

use App\Models\CustomerActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteCustomerActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('activity'));
    }

    public function rules(): array
    {
        return [
            'outcome' => ['nullable', 'string', Rule::in(CustomerActivity::OUTCOMES)],
        ];
    }
}
