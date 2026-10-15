<?php

namespace App\Http\Resources\AuditLog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $changes = null;

        if ($this->action === 'updated' && $this->old_values && $this->new_values) {
            $changes = [];
            foreach ($this->new_values as $key => $newVal) {
                $oldVal = $this->old_values[$key] ?? null;
                if ($oldVal != $newVal) {
                    $changes[$key] = ['old' => $oldVal, 'new' => $newVal];
                }
            }
        }

        return [
            'id'          => $this->id,
            'user'        => $this->whenLoaded('user', fn () => $this->user ? [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            'entity_type' => class_basename($this->auditable_type),
            'entity_id'   => $this->auditable_id,
            'action'      => $this->action,
            'changes'     => $changes,
            'old_values'  => $this->when($this->action !== 'updated', $this->old_values),
            'new_values'  => $this->when($this->action !== 'updated', $this->new_values),
            'ip_address'  => $this->ip_address,
            'created_at'  => $this->created_at->toISOString(),
        ];
    }
}
