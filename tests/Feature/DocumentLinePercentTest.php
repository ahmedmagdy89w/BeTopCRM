<?php

// ── Percent wins over amount ──────────────────────────────────────────────

test('percent discount computes the amount and wins over amount', function () {
    [$admin, $customer, $opportunity] = quotationOpportunity();
    $product = $opportunity->products()->first();

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/products/{$product->id}", [
            'discount_percent' => 10,
            'discount_amount' => 999,
        ])
        ->assertOk();

    // 10% of the 180.00 line gross = 18.00 (the 999 amount is ignored).
    $pivot = $opportunity->products()->first()->pivot;
    expect($pivot->discount_percent)->toBe('10.00')
        ->and($pivot->discount_amount)->toBe('18.00');
});

test('percent discount rejects values above 100', function () {
    [$admin, $customer, $opportunity] = quotationOpportunity();
    $product = $opportunity->products()->first();

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/products/{$product->id}", [
            'discount_percent' => 150,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['discount_percent']);
});

test('clearing percent resets to the given amount', function () {
    [$admin, $customer, $opportunity] = quotationOpportunity();
    $product = $opportunity->products()->first();

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/products/{$product->id}", [
            'discount_percent' => 10,
        ])
        ->assertOk();

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/products/{$product->id}", [
            'discount_percent' => null,
            'discount_amount' => 5,
        ])
        ->assertOk();

    $pivot = $opportunity->products()->first()->pivot;
    expect($pivot->discount_percent)->toBeNull()
        ->and($pivot->discount_amount)->toBe('5.00');
});

// ── End-to-end snapshots ──────────────────────────────────────────────────

test('quotation and invoice snapshot the discount percent', function () {
    [$admin, $customer, $opportunity] = quotationOpportunity();
    $product = $opportunity->products()->first();

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/products/{$product->id}", [
            'discount_percent' => 10,
        ])
        ->assertOk();

    // Gross 180, 10% = 18.00, no tax, total 162.00.
    $quotation = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertCreated()
        ->assertJsonPath('data.discount_amount', '18.00')
        ->assertJsonPath('data.total', '162.00')
        ->assertJsonPath('data.items.0.discount_percent', '10.00')
        ->assertJsonPath('data.items.0.discount_amount', '18.00')
        ->json('data');

    expect($quotation['items'][0]['discount_percent'])->toBe('10.00');

    $opportunity->update(['stage' => 'won']);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/invoice")
        ->assertCreated()
        ->assertJsonPath('data.discount_amount', '18.00')
        ->assertJsonPath('data.total', '162.00')
        ->assertJsonPath('data.items.0.discount_percent', '10.00');

    $this->actingAs($admin)
        ->get("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations/{$quotation['id']}/pdf")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
