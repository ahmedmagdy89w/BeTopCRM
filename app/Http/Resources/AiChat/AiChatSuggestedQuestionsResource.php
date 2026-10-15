<?php

namespace App\Http\Resources\AiChat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiChatSuggestedQuestionsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'questions' => $this->resource,
        ];
    }
}
