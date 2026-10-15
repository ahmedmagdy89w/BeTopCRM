<?php

namespace App\Http\Requests\Webhook;

use App\Models\WebhookEndpoint;
use App\Services\WebhookDestinationValidator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWebhookEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('webhook'));
    }

    public function rules(): array
    {
        return [
            'url' => ['sometimes', 'url', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! app(WebhookDestinationValidator::class)->isAllowed($value)) {
                    $fail(__('messages.webhook_url_public_https'));
                }
            }],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', 'in:'.implode(',', WebhookEndpoint::SUPPORTED_EVENTS)],
            'secret' => ['sometimes', 'nullable', 'string', 'min:32', 'max:255'],
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
