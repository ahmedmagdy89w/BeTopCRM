<?php

namespace App\Http\Resources\Webhook;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookEndpointResource extends JsonResource
{
    /** Whether to include the secret in the response (only on create/rotate). */
    public bool $withSecret = false;

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'url' => $this->url,
            'events' => $this->events,
            'is_active' => $this->is_active,
            'description' => $this->description,
            'last_triggered_at' => $this->last_triggered_at,
            'failure_count' => $this->failure_count,
            'created_at' => $this->created_at,
        ];

        if ($this->withSecret) {
            $data['secret'] = $this->secret;
        }

        return $data;
    }
}
