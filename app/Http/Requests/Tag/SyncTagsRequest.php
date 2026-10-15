<?php

namespace App\Http\Requests\Tag;

use Illuminate\Foundation\Http\FormRequest;

class SyncTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // entity-level authorization handled in controller
    }

    public function rules(): array
    {
        return [
            'tag_ids'   => ['required', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ];
    }
}
