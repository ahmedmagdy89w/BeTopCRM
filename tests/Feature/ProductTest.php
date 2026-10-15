<?php

use App\Models\CompanySetting;
use App\Models\Product;

// ── CRUD (happy path) ────────────────────────────────────────────────────────

test('admin can list products', function () {
    $admin = adminUser();
    Product::factory(3)->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});

test('admin can create a product', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/products', [
            'name' => 'Enterprise License',
            'sku' => 'ENT-001',
            'unit_price' => 999.99,
            'currency' => 'USD',
        ])
        ->assertCreated()
        ->assertJsonPath('data.sku', 'ENT-001');

    $this->assertDatabaseHas('products', ['sku' => 'ENT-001']);
});

test('product inherits settings currency when omitted', function () {
    $admin = adminUser();
    CompanySetting::setValue('company_currency', 'SAR');

    $this->actingAs($admin)
        ->postJson('/api/v1/products', [
            'name' => 'Local License',
            'unit_price' => 100,
        ])
        ->assertCreated()
        ->assertJsonPath('data.currency', 'SAR');
});

test('explicit product currency is ignored in favor of settings', function () {
    $admin = adminUser();
    CompanySetting::setValue('company_currency', 'SAR');

    $this->actingAs($admin)
        ->postJson('/api/v1/products', [
            'name' => 'Forced License',
            'unit_price' => 100,
            'currency' => 'GBP',
        ])
        ->assertCreated()
        ->assertJsonPath('data.currency', 'SAR');
});

test('admin can show a product', function () {
    $admin = adminUser();
    $product = Product::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $product->id);
});

test('admin can update a product', function () {
    $admin = adminUser();
    $product = Product::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->putJson("/api/v1/products/{$product->id}", ['name' => 'Updated Product'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Product');
});

test('admin can delete a product', function () {
    $admin = adminUser();
    $product = Product::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/products/{$product->id}")
        ->assertNoContent();

    $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false]);
});

test('admin can toggle product active status', function () {
    $admin = adminUser();
    $product = Product::factory()->create(['created_by' => $admin->id, 'is_active' => true]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/products/{$product->id}/toggle-active")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

// ── Filtering ────────────────────────────────────────────────────────────────

test('inactive products are excluded by default', function () {
    $admin = adminUser();
    $active = Product::factory()->create(['created_by' => $admin->id, 'is_active' => true]);
    $inactive = Product::factory()->inactive()->create(['created_by' => $admin->id]);

    $response = $this->actingAs($admin)
        ->getJson('/api/v1/products')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->toArray();
    expect($ids)->toContain($active->id)->not->toContain($inactive->id);
});

test('inactive products are shown when filter is applied', function () {
    $admin = adminUser();
    $inactive = Product::factory()->inactive()->create(['created_by' => $admin->id]);

    $response = $this->actingAs($admin)
        ->getJson('/api/v1/products?is_active=false')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->toArray();
    expect($ids)->toContain($inactive->id);
});

// ── Authorization ────────────────────────────────────────────────────────────

test('manager can view products', function () {
    $admin = adminUser();
    $manager = managerUser();
    Product::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($manager)
        ->getJson('/api/v1/products')
        ->assertOk();
});

test('sales_rep can view products', function () {
    $admin = adminUser();
    $rep = salesRepUser();
    Product::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($rep)
        ->getJson('/api/v1/products')
        ->assertOk();
});

test('sales_rep cannot create a product', function () {
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->postJson('/api/v1/products', [
            'name' => 'Rep Product',
            'sku' => 'REP-001',
            'unit_price' => 10.00,
        ])
        ->assertForbidden();
});

test('unauthenticated request on products returns 401', function () {
    $this->getJson('/api/v1/products')->assertUnauthorized();
});

// ── Validation ───────────────────────────────────────────────────────────────

test('create product requires name and unit_price', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/products', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'unit_price']);
});

test('sku must be unique', function () {
    $admin = adminUser();
    $product = Product::factory()->create(['created_by' => $admin->id, 'sku' => 'DUPE-001']);

    $this->actingAs($admin)
        ->postJson('/api/v1/products', [
            'name' => 'Another Product',
            'sku' => 'DUPE-001',
            'unit_price' => 50.00,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sku']);
});
