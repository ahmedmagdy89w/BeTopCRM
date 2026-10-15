<?php

namespace App\Http\Requests\Webhook;

use App\Models\WebhookEndpoint;
use App\Services\WebhookDestinationValidator;
use Illuminate\Foundation\Http\FormRequest;

class StoreWebhookEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', WebhookEndpoint::class);
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! app(WebhookDestinationValidator::class)->isAllowed($value)) {
                    $fail(__('messages.webhook_url_public_https'));
                }
            }],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:'.implode(',', WebhookEndpoint::SUPPORTED_EVENTS)],
            'secret' => ['nullable', 'string', 'min:32', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'secret.min' => __('messages.webhook_secret_minimum'),
        ];
    }
}
