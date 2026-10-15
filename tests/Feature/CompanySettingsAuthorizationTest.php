<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// ── Manager: view only ────────────────────────────────────────────────────────

test('manager can view company settings', function () {
    $manager = managerUser();

    $this->actingAs($manager)
        ->getJson('/api/v1/company/settings')
        ->assertOk();
});

test('manager cannot update company settings', function () {
    $manager = managerUser();

    $this->actingAs($manager)
        ->putJson('/api/v1/company/settings', ['company_name' => 'Hacked'])
        ->assertForbidden();
});

test('manager cannot reveal the company ai api key', function () {
    $this->actingAs(managerUser())
        ->postJson('/api/v1/company/settings/ai-key/reveal')
        ->assertForbidden();
});

test('manager cannot upload company logo', function () {
    Storage::fake('public');

    $manager = managerUser();
    $logo = UploadedFile::fake()->image('logo.png');

    $this->actingAs($manager)
        ->postJson('/api/v1/company/settings/logo', ['logo' => $logo])
        ->assertForbidden();
});

test('manager cannot delete company logo', function () {
    $manager = managerUser();

    $this->actingAs($manager)
        ->deleteJson('/api/v1/company/settings/logo')
        ->assertForbidden();
});

// ── Sales rep: no access ──────────────────────────────────────────────────────

test('sales_rep cannot view company settings', function () {
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->getJson('/api/v1/company/settings')
        ->assertForbidden();
});

test('sales_rep cannot update company settings', function () {
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->putJson('/api/v1/company/settings', ['company_name' => 'Hacked'])
        ->assertForbidden();
});

test('sales_rep cannot upload company logo', function () {
    Storage::fake('public');

    $rep = salesRepUser();
    $logo = UploadedFile::fake()->image('logo.png');

    $this->actingAs($rep)
        ->postJson('/api/v1/company/settings/logo', ['logo' => $logo])
        ->assertForbidden();
});

test('sales_rep cannot delete company logo', function () {
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->deleteJson('/api/v1/company/settings/logo')
        ->assertForbidden();
});
