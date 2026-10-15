<?php

namespace App\Http\Resources\WhatsApp;

use App\Http\Resources\CustomerActivity\CustomerActivityResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppSentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => $this->resource['success'],
            'dispatched_via' => $this->resource['dispatched_via'],
            'phone' => $this->resource['phone'],
            'message' => $this->resource['message'],
            'click_to_chat_url' => $this->resource['click_to_chat_url'] ?? null,
            'cloud_api_result' => $this->resource['cloud_api_result'] ?? null,
            'activity' => isset($this->resource['activity'])
                ? new CustomerActivityResource($this->resource['activity'])
                : null,
        ];
    }
}
