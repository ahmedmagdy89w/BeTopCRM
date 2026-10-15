<?php

namespace App\Http\Requests\EmailTemplate;

use App\Models\EmailTemplate;
use Illuminate\Foundation\Http\FormRequest;

class GenerateEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', EmailTemplate::class);
    }

    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'max:500'],
            'audience' => ['nullable', 'string', 'max:255'],
            'tone' => ['nullable', 'string', 'in:professional,friendly,urgent'],
            'context' => ['nullable', 'string', 'max:1000'],
            'language' => ['nullable', 'string', 'in:en,ar'],
        ];
    }
}
