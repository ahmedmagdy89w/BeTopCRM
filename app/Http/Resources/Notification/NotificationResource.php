<?php

namespace App\Http\Resources\Notification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->simplifyType($this->type),
            'data'       => $this->data,
            'read_at'    => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    private function simplifyType(string $fqcn): string
    {
        // App\Notifications\LeadAssignedNotification → lead_assigned
        $short = class_basename($fqcn);
        $short = preg_replace('/Notification$/', '', $short);
        return strtolower(preg_replace('/([A-Z])/', '_$1', lcfirst($short)));
    }
}
