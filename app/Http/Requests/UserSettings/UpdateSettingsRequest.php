<?php

namespace App\Http\Requests\UserSettings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'timezone' => ['sometimes', 'string', 'timezone'],
            'locale' => ['sometimes', 'string', Rule::in(['en', 'ar'])],
            'date_format' => ['sometimes', 'string', Rule::in(['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'd M Y', 'MM/DD/YYYY', 'DD/MM/YYYY'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'notification_preferences' => ['sometimes', 'array'],
            'notification_preferences.lead_assigned' => ['boolean'],
            'notification_preferences.lead_status_changed' => ['boolean'],
            'notification_preferences.follow_up_reminder' => ['boolean'],
            'notification_preferences.activity_reminder' => ['boolean'],
            'notification_preferences.opportunity_expiring' => ['boolean'],
            'notification_preferences.customer_health_alert' => ['boolean'],
            'notification_preferences.task_due' => ['boolean'],
        ];
    }
}
