<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'timezone' => $this->timezone ?? 'UTC',
            'locale' => $this->locale ?? 'en',
            'role' => $this->getRoleNames()->first(),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->getAllPermissions()->pluck('name')->values()),
            'is_active' => $this->is_active ?? true,
            'avatar_url' => $this->avatar_path ? asset(Storage::url($this->avatar_path)) : null,
            'stats' => $this->when(isset($this->assigned_customers_count), [
                'leads_assigned' => $this->assigned_customers_count,
                'customers_managed' => $this->managed_customers_count ?? 0,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
