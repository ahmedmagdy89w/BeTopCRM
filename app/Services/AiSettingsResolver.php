<?php

namespace App\Services;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Config;

class AiSettingsResolver
{
    /**
     * Resolve the effective AI provider configuration.
     *
     * Database settings take precedence over environment config so the
     * provider can be changed from the settings UI without redeploying.
     *
     * @return array{provider: string, model: string, key: ?string, url: ?string}
     */
    public function resolve(): array
    {
        $settings = CompanySetting::cachedAll();

        $provider = $settings[CompanySettingsService::AI_PROVIDER_KEY] ?? null;
        $provider = is_string($provider) && $provider !== '' ? $provider : Config::get('ai.default');

        $model = $settings[CompanySettingsService::AI_MODEL_KEY] ?? null;
        $model = is_string($model) && $model !== '' ? $model : Config::get('crm.ai.model');

        $url = $settings[CompanySettingsService::AI_BASE_URL_KEY] ?? null;
        $url = is_string($url) && $url !== '' ? $url : null;

        $key = CompanySettingsService::decryptApiKey($settings[CompanySettingsService::AI_API_KEY_KEY] ?? null);
        $key = $key ?? Config::get("ai.providers.{$provider}.key");

        return [
            'provider' => (string) $provider,
            'model' => (string) $model,
            'key' => is_string($key) && $key !== '' ? $key : null,
            'url' => $url,
        ];
    }

    /**
     * Apply the resolved configuration to the runtime config and return it.
     *
     * The override is process-local, so queued jobs and config:cache stay safe.
     *
     * @return array{provider: string, model: string, key: ?string, url: ?string}
     */
    public function applyToConfig(): array
    {
        $resolved = $this->resolve();

        Config::set('ai.default', $resolved['provider']);

        if ($resolved['key'] !== null) {
            Config::set("ai.providers.{$resolved['provider']}.key", $resolved['key']);
        }

        if ($resolved['url'] !== null) {
            Config::set("ai.providers.{$resolved['provider']}.url", $resolved['url']);
        }

        return $resolved;
    }
}
