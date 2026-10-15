<?php

use App\Models\CompanySetting;

// ── Settings defaults & persistence ─────────────────────────────────────────

test('feature flags default to enabled when unset', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson('/api/v1/company/settings')
        ->assertOk()
        ->assertJsonPath('data.features.quotations_enabled', true)
        ->assertJsonPath('data.features.invoices_enabled', true);
});

test('admin can disable and re-enable sales features', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->putJson('/api/v1/company/settings', [
            'features' => ['quotations_enabled' => false, 'invoices_enabled' => false],
        ])
        ->assertOk()
        ->assertJsonPath('data.features.quotations_enabled', false)
        ->assertJsonPath('data.features.invoices_enabled', false);

    expect(CompanySetting::getValue('quotations_enabled'))->toBe('0')
        ->and(CompanySetting::getValue('invoices_enabled'))->toBe('0');

    $this->actingAs($admin)
        ->putJson('/api/v1/company/settings', [
            'features' => ['quotations_enabled' => true, 'invoices_enabled' => true],
        ])
        ->assertOk()
        ->assertJsonPath('data.features.quotations_enabled', true)
        ->assertJsonPath('data.features.invoices_enabled', true);
});

test('feature flags reject non-boolean values', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->putJson('/api/v1/company/settings', [
            'features' => ['quotations_enabled' => 'yes-please'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['features.quotations_enabled']);
});

// ── Quotation enforcement ───────────────────────────────────────────────────

test('disabled quotations block list, create and read endpoints', function () {
    [$admin, $customer, $opportunity] = quotationOpportunity();

    $quotationId = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertCreated()
        ->json('data.id');

    CompanySetting::setValue('quotations_enabled', '0');

    $this->actingAs($admin)->getJson('/api/v1/quotations')->assertForbidden();
    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertForbidden();
    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations/{$quotationId}")
        ->assertForbidden();
    $this->actingAs($admin)
        ->get("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations/{$quotationId}/pdf")
        ->assertForbidden();
});

// ── Invoice enforcement ─────────────────────────────────────────────────────

test('disabled invoices block list, create and read endpoints', function () {
    [$admin, $customer, $opportunity] = quotationOpportunity();

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertCreated();

    $opportunity->update(['stage' => 'won']);

    $invoiceId = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/invoice")
        ->assertCreated()
        ->json('data.id');

    CompanySetting::setValue('invoices_enabled', '0');

    $this->actingAs($admin)->getJson('/api/v1/invoices')->assertForbidden();
    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/invoice")
        ->assertForbidden();
    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/invoice")
        ->assertForbidden();
    $this->actingAs($admin)
        ->get("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/invoice/{$invoiceId}/pdf")
        ->assertForbidden();
});
