<?php

namespace App\Http\Resources\OpportunityActivity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'opportunity_id' => $this->opportunity_id,
            'type' => $this->type,
            'channel' => $this->channel,
            'subject' => $this->subject,
            'description' => $this->description,
            'outcome' => $this->outcome,
            'due_date' => $this->due_date,
            'reminder_at' => $this->reminder_at,
            'reminder_sent_at' => $this->reminder_sent_at,
            'completed_at' => $this->completed_at,
            'is_completed' => $this->isCompleted(),
            'is_overdue' => $this->isOverdue(),
            'user' => $this->when($this->relationLoaded('user') && $this->user, fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
