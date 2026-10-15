<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class SendWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view-customers')
            && $this->user()->can('create-activities');
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'min:6', 'max:30'],
            'message' => ['required', 'string', 'min:1', 'max:4000'],
            'subject' => ['nullable', 'string', 'max:255'],
            'via_cloud_api' => ['sometimes', 'boolean'],
        ];
    }
}
