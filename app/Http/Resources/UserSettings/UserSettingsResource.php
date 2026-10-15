<?php

namespace App\Http\Resources\UserSettings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $preferences = $this->notification_preferences ?? $this->defaultPreferences();
        if (! array_key_exists('opportunity_expiring', $preferences)) {
            $preferences['opportunity_expiring'] = $preferences['deal_expiring'] ?? true;
        }
        unset($preferences['deal_expiring']);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'timezone' => $this->timezone ?? 'UTC',
            'locale' => $this->locale ?? 'en',
            'date_format' => $this->date_format ?? 'Y-m-d',
            'notification_preferences' => $preferences,
            'avatar_url' => $this->avatar_path
                ? asset(Storage::url($this->avatar_path))
                : null,
            'role' => $this->getRoleNames()->first(),
            'created_at' => $this->created_at,
        ];
    }

    private function defaultPreferences(): array
    {
        return [
            'lead_assigned' => true,
            'lead_status_changed' => true,
            'follow_up_reminder' => true,
            'activity_reminder' => true,
            'opportunity_expiring' => true,
            'customer_health_alert' => true,
            'task_due' => true,
        ];
    }
}
