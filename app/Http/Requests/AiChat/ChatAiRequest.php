<?php

namespace App\Http\Requests\AiChat;

use App\AI\AiChat;
use Illuminate\Foundation\Http\FormRequest;

class ChatAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AiChat::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:1000'],
            'scope' => ['nullable', 'string', 'in:all,reports,pipeline,customers,products'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_type' => ['nullable', 'string', 'in:individual,company'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'language' => ['nullable', 'string', 'in:en,ar'],
        ];
    }
}
