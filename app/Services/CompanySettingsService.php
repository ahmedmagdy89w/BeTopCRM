<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quotation;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanySettingsService
{
    private const LOGO_DISK = 'public';

    private const LOGO_DIRECTORY = 'company/logo';

    private const LOGO_KEY = 'company_logo_path';

    public const AI_PROVIDER_KEY = 'ai_provider';

    public const AI_MODEL_KEY = 'ai_model';

    public const AI_API_KEY_KEY = 'ai_api_key';

    public const AI_BASE_URL_KEY = 'ai_base_url';

    public const PRODUCT_SINGULAR_EN_KEY = 'product_singular_en';

    public const PRODUCT_PLURAL_EN_KEY = 'product_plural_en';

    public const PRODUCT_SINGULAR_AR_KEY = 'product_singular_ar';

    public const PRODUCT_PLURAL_AR_KEY = 'product_plural_ar';

    public const QUOTATIONS_ENABLED_KEY = 'quotations_enabled';

    public const INVOICES_ENABLED_KEY = 'invoices_enabled';

    /**
     * Text-capable Laravel AI drivers offered in the settings UI.
     *
     * @var string[]
     */
    public const AI_PROVIDERS = [
        'gemini',
        'openai',
        'anthropic',
        'ollama',
        'groq',
        'deepseek',
        'mistral',
        'openrouter',
        'xai',
        'azure',
    ];

    private const FIELD_KEYS = [
        'company_name',
        'company_email',
        'company_phone',
        'company_website',
        'company_tax_number',
        'company_currency',
        'company_timezone',
        'address_street',
        'address_city',
        'address_state',
        'address_country',
        'address_zip',
    ];

    public function get(): array
    {
        return $this->formatSettings(CompanySetting::cachedAll());
    }

    public function revealAiApiKey(): ?string
    {
        return self::decryptApiKey(CompanySetting::getValue(self::AI_API_KEY_KEY));
    }

    public function getForDocument(): array
    {
        $settings = CompanySetting::cachedAll();
        $company = $this->formatSettings($settings);
        $logoPath = $settings[self::LOGO_KEY] ?? null;
        $company['logo_file_path'] = $logoPath ? Storage::disk(self::LOGO_DISK)->path($logoPath) : null;

        return $company;
    }

    private function formatSettings(array $settings): array
    {
        $logoPath = $settings[self::LOGO_KEY] ?? null;

        return [
            'company_name' => $settings['company_name'] ?? null,
            'company_email' => $settings['company_email'] ?? null,
            'company_phone' => $settings['company_phone'] ?? null,
            'company_website' => $settings['company_website'] ?? null,
            'company_tax_number' => $settings['company_tax_number'] ?? null,
            'company_currency' => $settings['company_currency'] ?? null,
            'company_timezone' => $settings['company_timezone'] ?? null,
            'address' => [
                'street' => $settings['address_street'] ?? null,
                'city' => $settings['address_city'] ?? null,
                'state' => $settings['address_state'] ?? null,
                'country' => $settings['address_country'] ?? null,
                'zip' => $settings['address_zip'] ?? null,
            ],
            'logo_url' => $logoPath ? asset(Storage::disk(self::LOGO_DISK)->url($logoPath)) : null,
            'ai' => $this->formatAiSettings($settings),
            'product_labels' => $this->formatProductLabels($settings),
            'features' => [
                'quotations_enabled' => self::parseFeatureFlag($settings[self::QUOTATIONS_ENABLED_KEY] ?? null),
                'invoices_enabled' => self::parseFeatureFlag($settings[self::INVOICES_ENABLED_KEY] ?? null),
            ],
        ];
    }

    /**
     * Format AI provider settings for API responses.
     *
     * The standard settings payload includes only a presence flag and a
     * masked hint (last 4 chars) for the settings UI.
     *
     * @param  array<string, mixed>  $settings
     * @return array{provider: ?string, model: ?string, base_url: ?string, api_key_set: bool, api_key_masked: ?string}
     */
    private function formatAiSettings(array $settings): array
    {
        $plainKey = self::decryptApiKey($settings[self::AI_API_KEY_KEY] ?? null);

        return [
            'provider' => $settings[self::AI_PROVIDER_KEY] ?? null,
            'model' => $settings[self::AI_MODEL_KEY] ?? null,
            'base_url' => $settings[self::AI_BASE_URL_KEY] ?? null,
            'api_key_set' => $plainKey !== null && $plainKey !== '',
            'api_key_masked' => $plainKey ? '****'.substr($plainKey, -4) : null,
        ];
    }

    /**
     * Format product terminology labels for API responses.
     *
     * Null values mean "use the built-in i18n strings".
     *
     * @param  array<string, mixed>  $settings
     * @return array{singular_en: ?string, plural_en: ?string, singular_ar: ?string, plural_ar: ?string}
     */
    private function formatProductLabels(array $settings): array
    {
        $pick = fn (string $key) => isset($settings[$key]) && is_string($settings[$key]) && trim($settings[$key]) !== ''
            ? trim($settings[$key])
            : null;

        return [
            'singular_en' => $pick(self::PRODUCT_SINGULAR_EN_KEY),
            'plural_en' => $pick(self::PRODUCT_PLURAL_EN_KEY),
            'singular_ar' => $pick(self::PRODUCT_SINGULAR_AR_KEY),
            'plural_ar' => $pick(self::PRODUCT_PLURAL_AR_KEY),
        ];
    }

    /**
     * Parse a feature flag value. Missing keys default to enabled (true)
     * so existing installs keep current behavior.
     */
    private static function parseFeatureFlag(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
        }

        return (bool) $value;
    }

    /**
     * Whether quotations are enabled for this installation.
     */
    public static function isQuotationsEnabled(): bool
    {
        return self::parseFeatureFlag(CompanySetting::cachedAll()[self::QUOTATIONS_ENABLED_KEY] ?? null);
    }

    /**
     * Whether invoices are enabled for this installation.
     */
    public static function isInvoicesEnabled(): bool
    {
        return self::parseFeatureFlag(CompanySetting::cachedAll()[self::INVOICES_ENABLED_KEY] ?? null);
    }

    /**
     * Resolve the effective product labels for a locale from the cache.
     *
     * Falls back to the built-in translation strings when no custom
     * label is configured — pass the fallbacks from the caller so this
     * stays free of HTTP/i18n coupling.
     *
     * @return array{singular: string, plural: string}
     */
    public static function productLabels(?string $locale = null, string $fallbackSingular = 'Product', string $fallbackPlural = 'Products'): array
    {
        $locale = $locale === 'ar' ? 'ar' : 'en';
        $settings = CompanySetting::cachedAll();

        $singularKey = $locale === 'ar' ? self::PRODUCT_SINGULAR_AR_KEY : self::PRODUCT_SINGULAR_EN_KEY;
        $pluralKey = $locale === 'ar' ? self::PRODUCT_PLURAL_AR_KEY : self::PRODUCT_PLURAL_EN_KEY;

        $singular = $settings[$singularKey] ?? null;
        $plural = $settings[$pluralKey] ?? null;

        return [
            'singular' => is_string($singular) && trim($singular) !== '' ? trim($singular) : $fallbackSingular,
            'plural' => is_string($plural) && trim($plural) !== '' ? trim($plural) : $fallbackPlural,
        ];
    }

    /**
     * Report title for the product-performance report, label-aware.
     */
    public static function productReportTitle(?string $locale = null): string
    {
        $locale = $locale === 'ar' ? 'ar' : 'en';
        $settings = CompanySetting::cachedAll();
        $key = $locale === 'ar' ? self::PRODUCT_PLURAL_AR_KEY : self::PRODUCT_PLURAL_EN_KEY;
        $plural = $settings[$key] ?? null;

        if (is_string($plural) && trim($plural) !== '') {
            $plural = trim($plural);

            return $locale === 'ar' ? "تقرير أداء {$plural}" : "{$plural} Performance Report";
        }

        return __('reports.product_performance_title');
    }

    /**
     * The effective default currency for new records.
     *
     * Single source of truth: the settings currency, uppercased, with a
     * hardcoded USD fallback when nothing is configured.
     */
    public static function defaultCurrency(): string
    {
        $code = CompanySetting::getValue('company_currency');

        return is_string($code) && $code !== '' ? strtoupper($code) : 'USD';
    }

    /**
     * Decrypt a stored AI API key. Falls back to the raw value for
     * legacy plain-text rows so they keep working until re-saved.
     */
    public static function decryptApiKey(mixed $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        try {
            $decrypted = Crypt::decryptString((string) $stored);
        } catch (DecryptException) {
            return (string) $stored;
        }

        return $decrypted === '' ? null : $decrypted;
    }

    public function update(array $data): array
    {
        $keyValues = [];

        $directKeys = [
            'company_name', 'company_email', 'company_phone',
            'company_website', 'company_tax_number', 'company_currency', 'company_timezone',
        ];

        foreach ($directKeys as $key) {
            if (array_key_exists($key, $data)) {
                $keyValues[$key] = $data[$key];
            }
        }

        if (isset($data['address']) && is_array($data['address'])) {
            foreach ($data['address'] as $field => $value) {
                $keyValues["address_{$field}"] = $value;
            }
        }

        if (isset($data['ai']) && is_array($data['ai'])) {
            $ai = $data['ai'];

            $aiFieldKeys = [
                'provider' => self::AI_PROVIDER_KEY,
                'model' => self::AI_MODEL_KEY,
                'base_url' => self::AI_BASE_URL_KEY,
            ];

            foreach ($aiFieldKeys as $field => $key) {
                if (array_key_exists($field, $ai)) {
                    $keyValues[$key] = $ai[$field];
                }
            }

            // Write-only: an absent or empty key keeps the stored value (v1 has no clear-key action).
            if (array_key_exists('api_key', $ai) && is_string($ai['api_key']) && trim($ai['api_key']) !== '') {
                $keyValues[self::AI_API_KEY_KEY] = Crypt::encryptString($ai['api_key']);
            }
        }

        if (isset($data['product_labels']) && is_array($data['product_labels'])) {
            $labelFieldKeys = [
                'singular_en' => self::PRODUCT_SINGULAR_EN_KEY,
                'plural_en' => self::PRODUCT_PLURAL_EN_KEY,
                'singular_ar' => self::PRODUCT_SINGULAR_AR_KEY,
                'plural_ar' => self::PRODUCT_PLURAL_AR_KEY,
            ];

            foreach ($labelFieldKeys as $field => $key) {
                if (array_key_exists($field, $data['product_labels'])) {
                    $value = $data['product_labels'][$field];
                    $keyValues[$key] = is_string($value) && trim($value) !== '' ? trim($value) : null;
                }
            }
        }

        if (isset($data['features']) && is_array($data['features'])) {
            foreach (['quotations_enabled' => self::QUOTATIONS_ENABLED_KEY, 'invoices_enabled' => self::INVOICES_ENABLED_KEY] as $field => $key) {
                if (array_key_exists($field, $data['features'])) {
                    $keyValues[$key] = $data['features'][$field] ? '1' : '0';
                }
            }
        }

        if (array_key_exists('company_currency', $keyValues) && is_string($keyValues['company_currency']) && $keyValues['company_currency'] !== '') {
            $keyValues['company_currency'] = strtoupper($keyValues['company_currency']);
        }

        $previousCurrency = CompanySetting::getValue('company_currency');

        if (! empty($keyValues)) {
            CompanySetting::setMany($keyValues);
        }

        $newCurrency = $keyValues['company_currency'] ?? null;

        if (is_string($newCurrency) && $newCurrency !== '' && $newCurrency !== $previousCurrency) {
            $this->backfillCurrency($newCurrency);
        }

        return $this->get();
    }

    /**
     * Convert all live records to a newly selected settings currency.
     *
     * Query-builder updates bypass Eloquent events, so no audit entries are written.
     */
    private function backfillCurrency(string $code): void
    {
        Opportunity::query()->update(['currency' => $code]);
        Product::query()->update(['currency' => $code]);
        Quotation::query()->update(['currency' => $code]);
        Invoice::query()->update(['currency' => $code]);
    }

    public function uploadLogo(UploadedFile $file): array
    {
        $existing = CompanySetting::getValue(self::LOGO_KEY);

        if ($existing) {
            Storage::disk(self::LOGO_DISK)->delete($existing);
        }

        $extension = $file->getClientOriginalExtension();
        $path = $file->storeAs(self::LOGO_DIRECTORY, Str::uuid().'.'.$extension, self::LOGO_DISK);
        CompanySetting::setValue(self::LOGO_KEY, $path);

        return $this->get();
    }

    public function deleteLogo(): void
    {
        $path = CompanySetting::getValue(self::LOGO_KEY);

        if ($path) {
            Storage::disk(self::LOGO_DISK)->delete($path);
            CompanySetting::where('key', self::LOGO_KEY)->delete();
            CompanySetting::forgetCache();
        }
    }
}
