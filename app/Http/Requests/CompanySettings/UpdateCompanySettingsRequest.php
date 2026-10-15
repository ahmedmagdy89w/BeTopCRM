<?php

namespace App\Http\Requests\CompanySettings;

use App\Models\CompanySetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', CompanySetting::class);
    }

    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'string', 'max:255'],
            'company_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'company_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'company_website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'company_tax_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'company_currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'company_timezone' => ['sometimes', 'nullable', 'string', 'timezone'],
            'address' => ['sometimes', 'array'],
            'address.street' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.zip' => ['sometimes', 'nullable', 'string', 'max:20'],
            'ai' => ['sometimes', 'array'],
            'ai.provider' => ['sometimes', 'nullable', 'string', 'in:gemini,openai,anthropic,ollama,groq,deepseek,mistral,openrouter,xai,azure'],
            'ai.model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'ai.api_key' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'ai.base_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'product_labels' => ['sometimes', 'array'],
            'product_labels.singular_en' => ['sometimes', 'nullable', 'string', 'max:60'],
            'product_labels.plural_en' => ['sometimes', 'nullable', 'string', 'max:60'],
            'product_labels.singular_ar' => ['sometimes', 'nullable', 'string', 'max:60'],
            'product_labels.plural_ar' => ['sometimes', 'nullable', 'string', 'max:60'],
            'features' => ['sometimes', 'array'],
            'features.quotations_enabled' => ['sometimes', 'boolean'],
            'features.invoices_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
