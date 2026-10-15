<?php

use App\Models\Product;
use App\Models\Tax;
use App\Models\User;

// ── CRUD ────────────────────────────────────────────────────────────────────

test('admin can manage taxes', function () {
    $admin = adminUser();

    $tax = $this->actingAs($admin)
        ->postJson('/api/v1/taxes', ['name' => 'VAT 14', 'percent' => 14])
        ->assertCreated()
        ->assertJsonPath('data.name', 'VAT 14')
        ->assertJsonPath('data.percent', '14.00')
        ->json('data');

    $this->actingAs($admin)
        ->getJson('/api/v1/taxes')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($admin)
        ->getJson("/api/v1/taxes/{$tax['id']}")
        ->assertOk()
        ->assertJsonPath('data.name', 'VAT 14');

    $this->actingAs($admin)
        ->putJson("/api/v1/taxes/{$tax['id']}", ['percent' => 15])
        ->assertOk()
        ->assertJsonPath('data.percent', '15.00');

    $this->actingAs($admin)
        ->deleteJson("/api/v1/taxes/{$tax['id']}")
        ->assertNoContent();

    $this->assertDatabaseMissing('taxes', ['id' => $tax['id']]);
});

test('tax validation rejects bad input', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/taxes', ['name' => '', 'percent' => 150])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'percent']);

    Tax::factory()->create(['name' => 'VAT 14', 'created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson('/api/v1/taxes', ['name' => 'VAT 14', 'percent' => 5])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('tax cannot be deleted while products use it', function () {
    $admin = adminUser();
    $tax = Tax::factory()->create(['created_by' => $admin->id]);
    Product::factory()->create(['created_by' => $admin->id, 'tax_id' => $tax->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/taxes/{$tax->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tax']);
});

// ── Authorization ─────────────────────────────────────────────────────────

test('tax endpoints require taxes permissions', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($user)->getJson('/api/v1/taxes')->assertForbidden();
    $this->actingAs($user)->postJson('/api/v1/taxes', ['name' => 'X', 'percent' => 1])->assertForbidden();
});

test('sales rep can view but not manage taxes', function () {
    $rep = salesRepUser();
    $tax = Tax::factory()->create(['created_by' => $rep->id]);

    $this->actingAs($rep)->getJson('/api/v1/taxes')->assertOk();
    $this->actingAs($rep)->postJson('/api/v1/taxes', ['name' => 'Y', 'percent' => 1])->assertForbidden();
    $this->actingAs($rep)->deleteJson("/api/v1/taxes/{$tax->id}")->assertForbidden();
});

// ── Products ──────────────────────────────────────────────────────────────

test('product rejects an inactive tax', function () {
    $admin = adminUser();
    $tax = Tax::factory()->create(['is_active' => false, 'created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson('/api/v1/products', [
            'name' => 'Taxed Service',
            'unit_price' => 100,
            'tax_id' => $tax->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tax_id']);
});

test('product exposes its tax', function () {
    $admin = adminUser();
    $tax = Tax::factory()->create(['name' => 'VAT 14', 'percent' => 14, 'created_by' => $admin->id]);

    $product = $this->actingAs($admin)
        ->postJson('/api/v1/products', [
            'name' => 'Taxed Service',
            'unit_price' => 100,
            'tax_id' => $tax->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.tax.name', 'VAT 14')
        ->json('data');

    $this->actingAs($admin)
        ->getJson("/api/v1/products/{$product['id']}")
        ->assertOk()
        ->assertJsonPath('data.tax.percent', '14.00');
});
