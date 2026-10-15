<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;

class GlobalSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'types' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function getTypes(): array
    {
        $allowed = ['leads', 'customers', 'opportunities'];

        if (empty($this->types)) {
            return $allowed;
        }

        return array_intersect(
            array_map('trim', explode(',', $this->types)),
            $allowed
        );
    }

    public function getLimit(): int
    {
        return (int) ($this->limit ?? 5);
    }
}
