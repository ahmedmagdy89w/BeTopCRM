<?php

use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Product;

function makeOppWithCustomer(): Opportunity
{
    $admin = adminUser();
    $customer = Customer::factory()->create(['created_by' => $admin->id]);

    return Opportunity::factory()->create([
        'customer_id' => $customer->id,
        'created_by' => $admin->id,
    ]);
}

test('admin can list opportunity products', function () {
    $admin = adminUser();
    $opp = makeOppWithCustomer();
    $product = Product::factory()->create(['created_by' => $admin->id]);
    $opp->products()->attach($product->id, ['quantity' => 2]);

    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/products")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.quantity', 2);
});

test('admin can attach product to opportunity', function () {
    $admin = adminUser();
    $opp = makeOppWithCustomer();
    $product = Product::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/products", [
            'product_id' => $product->id,
            'quantity' => 3,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('opportunity_products', [
        'opportunity_id' => $opp->id,
        'product_id' => $product->id,
        'quantity' => 3,
    ]);
});

test('admin can attach product with custom price', function () {
    $admin = adminUser();
    $opp = makeOppWithCustomer();
    $product = Product::factory()->create(['created_by' => $admin->id, 'unit_price' => 100]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/products", [
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 80.50,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('opportunity_products', [
        'opportunity_id' => $opp->id,
        'product_id' => $product->id,
        'unit_price' => 80.50,
    ]);
});

test('attach without price defaults to catalog price', function () {
    $admin = adminUser();
    $opp = makeOppWithCustomer();
    $product = Product::factory()->create(['created_by' => $admin->id, 'unit_price' => 120]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/products", [
            'product_id' => $product->id,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('opportunity_products', [
        'opportunity_id' => $opp->id,
        'product_id' => $product->id,
        'unit_price' => 120,
    ]);
});

test('admin can update opportunity product quantity', function () {
    $admin = adminUser();
    $opp = makeOppWithCustomer();
    $product = Product::factory()->create(['created_by' => $admin->id]);
    $opp->products()->attach($product->id, ['quantity' => 1]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/products/{$product->id}", [
            'quantity' => 5,
        ])
        ->assertOk();

    $this->assertDatabaseHas('opportunity_products', [
        'opportunity_id' => $opp->id,
        'product_id' => $product->id,
        'quantity' => 5,
    ]);
});

test('admin can detach product from opportunity', function () {
    $admin = adminUser();
    $opp = makeOppWithCustomer();
    $product = Product::factory()->create(['created_by' => $admin->id]);
    $opp->products()->attach($product->id);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/products/{$product->id}")
        ->assertOk();

    $this->assertDatabaseMissing('opportunity_products', [
        'opportunity_id' => $opp->id,
        'product_id' => $product->id,
    ]);
});

test('attaching product recalculates opportunity amount', function () {
    $admin = adminUser();
    $opp = makeOppWithCustomer();
    $product = Product::factory()->create(['created_by' => $admin->id, 'unit_price' => 100]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/products", [
            'product_id' => $product->id,
            'quantity' => 3,
        ])
        ->assertCreated();

    expect($opp->fresh()->amount)->toEqual('300.00');
});

test('attaching with custom price uses pivot price for amount', function () {
    $admin = adminUser();
    $opp = makeOppWithCustomer();
    $product = Product::factory()->create(['created_by' => $admin->id, 'unit_price' => 100]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/products", [
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 80.50,
        ])
        ->assertCreated();

    expect($opp->fresh()->amount)->toEqual('161.00');
});

test('updating product quantity recalculates opportunity amount', function () {
    $admin = adminUser();
    $opp = makeOppWithCustomer();
    $product = Product::factory()->create(['created_by' => $admin->id, 'unit_price' => 50]);
    $opp->products()->attach($product->id, ['quantity' => 1, 'unit_price' => 50]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/products/{$product->id}", [
            'quantity' => 4,
        ])
        ->assertOk();

    expect($opp->fresh()->amount)->toEqual('200.00');
});

test('detaching last product resets opportunity amount to zero', function () {
    $admin = adminUser();
    $opp = makeOppWithCustomer();
    $product = Product::factory()->create(['created_by' => $admin->id, 'unit_price' => 50]);
    $opp->products()->attach($product->id, ['quantity' => 2, 'unit_price' => 50]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/products/{$product->id}")
        ->assertOk();

    expect($opp->fresh()->amount)->toEqual('0.00');
});

test('unauthenticated request on opportunity products returns 401', function () {
    $this->getJson('/api/v1/customers/1/opportunities/1/products')->assertUnauthorized();
});
