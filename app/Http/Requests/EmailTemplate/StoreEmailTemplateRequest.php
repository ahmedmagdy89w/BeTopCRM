<?php

namespace App\Http\Requests\EmailTemplate;

use App\Models\EmailTemplate;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', EmailTemplate::class);
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string'],
        ];
    }
}
