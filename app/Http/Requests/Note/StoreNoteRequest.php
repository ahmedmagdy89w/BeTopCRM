<?php

namespace App\Http\Requests\Note;

use Illuminate\Foundation\Http\FormRequest;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'body'      => ['required', 'string', 'max:10000'],
            'parent_id' => ['nullable', 'exists:notes,id'],
            'is_pinned' => ['boolean'],
        ];
    }
}
