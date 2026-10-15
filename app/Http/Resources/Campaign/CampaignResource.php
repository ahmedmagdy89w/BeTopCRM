<?php

namespace App\Http\Resources\Campaign;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'template'        => $this->whenLoaded('template', fn () => [
                'id'   => $this->template->id,
                'name' => $this->template->name,
            ]),
            'status'          => $this->status,
            'segment_filters' => $this->segment_filters,
            'stats'           => [
                'total_recipients' => $this->recipients_count ?? $this->recipientCount(),
                'sent'             => $this->sentCount(),
                'failed'           => $this->failedCount(),
                'pending'          => ($this->recipients_count ?? $this->recipientCount())
                                     - $this->sentCount() - $this->failedCount(),
            ],
            'scheduled_at'    => $this->scheduled_at?->toISOString(),
            'sent_at'         => $this->sent_at?->toISOString(),
            'created_at'      => $this->created_at->toISOString(),
            'updated_at'      => $this->updated_at->toISOString(),
        ];
    }
}
