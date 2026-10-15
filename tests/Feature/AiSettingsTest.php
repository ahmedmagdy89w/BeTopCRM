<?php

use App\Models\CompanySetting;
use App\Services\AiSettingsResolver;
use App\Services\CompanySettingsService;
use Illuminate\Support\Facades\Crypt;

// ── PUT /api/v1/company/settings (AI section) ────────────────────────────────

test('admin can update ai provider settings', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->putJson('/api/v1/company/settings', [
            'ai' => [
                'provider' => 'gemini',
                'model' => 'gemini-2.0-flash',
                'api_key' => 'secret-key-12345678',
                'base_url' => null,
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.ai.provider', 'gemini')
        ->assertJsonPath('data.ai.model', 'gemini-2.0-flash')
        ->assertJsonPath('data.ai.api_key_set', true)
        ->assertJsonPath('data.ai.api_key_masked', '****5678');

    expect(CompanySetting::getValue('ai_provider'))->toBe('gemini')
        ->and(CompanySetting::getValue('ai_model'))->toBe('gemini-2.0-flash');
});

test('stored ai api key is encrypted and omitted from standard settings responses', function () {
    $admin = adminUser();
    $plain = 'super-secret-key-abcdef12';

    $response = $this->actingAs($admin)
        ->putJson('/api/v1/company/settings', ['ai' => ['api_key' => $plain]])
        ->assertOk();

    expect(CompanySetting::getValue('ai_api_key'))->not->toBe($plain)
        ->and(Crypt::decryptString(CompanySetting::getValue('ai_api_key')))->toBe($plain);

    $body = $response->json('data');
    expect(json_encode($body))->not->toContain($plain);

    $this->actingAs($admin)
        ->getJson('/api/v1/company/settings')
        ->assertOk()
        ->assertJsonPath('data.ai.api_key_set', true);

    $getBody = $this->actingAs($admin)->getJson('/api/v1/company/settings')->json('data');
    expect(json_encode($getBody))->not->toContain($plain);
});

test('admin can reveal the stored ai api key on demand', function () {
    $plain = 'revealable-secret-87654321';
    CompanySetting::setValue('ai_api_key', Crypt::encryptString($plain));

    $this->actingAs(adminUser())
        ->postJson('/api/v1/company/settings/ai-key/reveal')
        ->assertOk()
        ->assertJsonPath('data.api_key', $plain);
});

test('unauthenticated request cannot reveal the stored ai api key', function () {
    $this->postJson('/api/v1/company/settings/ai-key/reveal')
        ->assertUnauthorized();
});

test('empty ai api key keeps the stored value', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->putJson('/api/v1/company/settings', ['ai' => ['api_key' => 'keep-me-12345678']])
        ->assertOk();

    $this->actingAs($admin)
        ->putJson('/api/v1/company/settings', ['company_name' => 'ACME'])
        ->assertOk()
        ->assertJsonPath('data.ai.api_key_set', true)
        ->assertJsonPath('data.ai.api_key_masked', '****5678');
});

test('update ai settings fails with invalid provider', function () {
    $this->actingAs(adminUser())
        ->putJson('/api/v1/company/settings', ['ai' => ['provider' => 'not-a-driver']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ai.provider']);
});

test('update ai settings fails with invalid base url', function () {
    $this->actingAs(adminUser())
        ->putJson('/api/v1/company/settings', ['ai' => ['base_url' => 'not-a-url']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ai.base_url']);
});

// ── Authorization ────────────────────────────────────────────────────────────

test('manager cannot update ai provider settings', function () {
    $this->actingAs(managerUser())
        ->putJson('/api/v1/company/settings', ['ai' => ['provider' => 'gemini']])
        ->assertForbidden();
});

test('unauthenticated request to update ai settings returns 401', function () {
    $this->putJson('/api/v1/company/settings', ['ai' => ['provider' => 'gemini']])
        ->assertUnauthorized();
});

// ── Resolver ─────────────────────────────────────────────────────────────────

test('ai resolver falls back to env config when database settings are empty', function () {
    config(['ai.default' => 'ollama', 'crm.ai.model' => 'gemma3:4b']);

    $resolved = app(AiSettingsResolver::class)->resolve();

    expect($resolved['provider'])->toBe('ollama')
        ->and($resolved['model'])->toBe('gemma3:4b');
});

test('ai resolver prefers database settings over env config', function () {
    config(['ai.default' => 'ollama', 'crm.ai.model' => 'gemma3:4b']);
    CompanySetting::setValue('ai_provider', 'gemini');
    CompanySetting::setValue('ai_model', 'gemini-2.0-flash');

    $resolved = app(AiSettingsResolver::class)->resolve();

    expect($resolved['provider'])->toBe('gemini')
        ->and($resolved['model'])->toBe('gemini-2.0-flash');
});

test('ai resolver decrypts the stored api key', function () {
    CompanySetting::setValue('ai_provider', 'gemini');
    CompanySetting::setValue('ai_api_key', Crypt::encryptString('db-key-xyz'));

    $resolved = app(AiSettingsResolver::class)->resolve();

    expect($resolved['key'])->toBe('db-key-xyz');
});

test('company settings response includes masked ai section by default', function () {
    $this->actingAs(adminUser())
        ->getJson('/api/v1/company/settings')
        ->assertOk()
        ->assertJsonStructure(['data' => ['ai' => [
            'provider',
            'model',
            'base_url',
            'api_key_set',
            'api_key_masked',
        ]]])
        ->assertJsonPath('data.ai.api_key_set', false)
        ->assertJsonPath('data.ai.api_key_masked', null);

    expect(app(CompanySettingsService::class)->get()['ai']['api_key_set'])->toBeFalse();
});
