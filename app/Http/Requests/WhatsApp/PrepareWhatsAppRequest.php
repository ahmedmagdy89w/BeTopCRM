<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class PrepareWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view-customers');
    }

    public function rules(): array
    {
        return [
            'custom_message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
