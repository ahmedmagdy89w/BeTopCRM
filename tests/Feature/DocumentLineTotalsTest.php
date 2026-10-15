<?php

use App\Models\Product;
use App\Models\Tax;

test('opportunity lines and quotation items follow insertion order', function () {
    [$admin, $customer, $opportunity] = quotationOpportunity();
    $first = $opportunity->products()->first();

    $second = Product::factory()->create(['created_by' => $admin->id, 'unit_price' => 50, 'currency' => 'USD']);
    $opportunity->products()->attach($second->id, ['quantity' => 1, 'unit_price' => 50, 'discount_amount' => 0]);
    $third = Product::factory()->create(['created_by' => $admin->id, 'unit_price' => 70, 'currency' => 'USD']);
    $opportunity->products()->attach($third->id, ['quantity' => 1, 'unit_price' => 70, 'discount_amount' => 0]);

    $listed = $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/products")
        ->assertOk()
        ->json('data');

    expect(array_column($listed, 'id'))->toBe([$first->id, $second->id, $third->id]);

    $quotation = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertCreated()
        ->json('data');

    expect(array_column($quotation['items'], 'product_id'))->toBe([$first->id, $second->id, $third->id]);
});

// ── Opportunity line discounts ────────────────────────────────────────────

test('opportunity product lines persist discounts capped at line gross', function () {
    [$admin, $customer, $opportunity] = quotationOpportunity();
    $product = $opportunity->products()->first();

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/products/{$product->id}", [
            'discount_amount' => 30,
        ])
        ->assertOk();

    expect($opportunity->products()->first()->pivot->discount_amount)->toBe('30.00');

    // Discount beyond the 180.00 line gross is capped.
    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/products/{$product->id}", [
            'discount_amount' => 9999,
        ])
        ->assertOk();

    expect((float) $opportunity->products()->first()->pivot->discount_amount)->toBe(180.0);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/products/{$product->id}", [
            'discount_amount' => -5,
        ])
        ->assertUnprocessable();
});

// ── Quotation line totals ─────────────────────────────────────────────────

test('quotation derives totals from line discounts and product taxes', function () {
    [$admin, $customer, $opportunity] = quotationOpportunity();
    $taxed = $opportunity->products()->first();

    $tax = Tax::factory()->create(['name' => 'VAT 14', 'percent' => 14, 'created_by' => $admin->id]);
    $taxed->update(['tax_id' => $tax->id]);
    $opportunity->products()->updateExistingPivot($taxed->id, ['discount_amount' => 20]);

    // Second line: no tax, no discount. Gross 1 × 50 = 50.
    $plain = Product::factory()->create(['created_by' => $admin->id, 'unit_price' => 50, 'currency' => 'USD']);
    $opportunity->products()->attach($plain->id, ['quantity' => 1, 'unit_price' => 50, 'discount_amount' => 0]);

    $quotation = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertCreated()
        // subtotal 180 + 50, discount 20, tax 14% of 160 = 22.40
        ->assertJsonPath('data.subtotal', '230.00')
        ->assertJsonPath('data.discount_amount', '20.00')
        ->assertJsonPath('data.tax_amount', '22.40')
        ->assertJsonPath('data.total', '232.40')
        ->json('data');

    $items = collect($quotation['items'])->keyBy('product_id');
    expect($items[$taxed->id]['tax_name'])->toBe('VAT 14')
        ->and($items[$taxed->id]['tax_percent'])->toBe('14.00')
        ->and($items[$taxed->id]['discount_amount'])->toBe('20.00')
        ->and($items[$plain->id]['tax_amount'])->toBe('0.00');
});

// ── Invoice copies line snapshots ─────────────────────────────────────────

test('invoice copies quotation line discounts and taxes', function () {
    [$admin, $customer, $opportunity] = quotationOpportunity();
    $product = $opportunity->products()->first();

    $tax = Tax::factory()->create(['name' => 'VAT 10', 'percent' => 10, 'created_by' => $admin->id]);
    $product->update(['tax_id' => $tax->id]);
    $opportunity->products()->updateExistingPivot($product->id, ['discount_amount' => 30]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertCreated();

    $opportunity->update(['stage' => 'won']);

    // Gross 180 − discount 30 = 150 taxable, 10% = 15.00, total 165.00.
    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/invoice")
        ->assertCreated()
        ->assertJsonPath('data.subtotal', '180.00')
        ->assertJsonPath('data.discount_amount', '30.00')
        ->assertJsonPath('data.tax_amount', '15.00')
        ->assertJsonPath('data.total', '165.00')
        ->assertJsonPath('data.items.0.tax_name', 'VAT 10');
});
