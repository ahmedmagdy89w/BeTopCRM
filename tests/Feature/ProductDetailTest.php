<?php

use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;

test('product show returns category creator and both custom field scopes', function () {
    $admin = adminUser();
    $category = ProductCategory::factory()->create(['created_by' => $admin->id]);
    $product = Product::factory()->create([
        'created_by' => $admin->id,
        'category_id' => $category->id,
        'custom_fields' => ['size' => 'model size'],
        'category_custom_fields' => ['size' => 'category size'],
    ]);

    $this->actingAs($admin)
        ->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.category.id', $category->id)
        ->assertJsonPath('data.created_by.id', $admin->id)
        ->assertJsonPath('data.custom_fields.size', 'model size')
        ->assertJsonPath('data.category_custom_fields.size', 'category size');
});

test('product opportunities endpoint returns related opportunities and pivot values', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['created_by' => $admin->id]);
    $product = Product::factory()->create(['created_by' => $admin->id]);
    $opportunity = Opportunity::factory()->create([
        'customer_id' => $customer->id,
        'created_by' => $admin->id,
        'title' => 'New Store Setup',
    ]);

    $opportunity->products()->attach($product->id, [
        'quantity' => 4,
        'unit_price' => '25.00',
        'notes' => 'Opportunity product note',
    ]);

    $this->actingAs($admin)
        ->getJson("/api/v1/products/{$product->id}/opportunities")
        ->assertOk()
        ->assertJsonPath('data.0.id', $opportunity->id)
        ->assertJsonPath('data.0.title', 'New Store Setup')
        ->assertJsonPath('data.0.customer.id', $customer->id)
        ->assertJsonPath('data.0.product_pivot.quantity', 4)
        ->assertJsonPath('data.0.product_pivot.unit_price', '25.00');
});

test('opportunity details return attached product interest values', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['created_by' => $admin->id]);
    $product = Product::factory()->create(['created_by' => $admin->id]);
    $opportunity = Opportunity::factory()->create([
        'customer_id' => $customer->id,
        'created_by' => $admin->id,
    ]);
    $opportunity->products()->attach($product->id, [
        'quantity' => 3,
        'unit_price' => '40.00',
        'notes' => 'Customer interest',
    ]);

    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.products.0.id', $product->id)
        ->assertJsonPath('data.products.0.quantity', 3)
        ->assertJsonPath('data.products.0.unit_price', '40.00')
        ->assertJsonPath('data.products.0.notes', 'Customer interest');
});

test('a user without view-products cannot show a product or load its related records', function () {
    $product = Product::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson("/api/v1/products/{$product->id}")
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson("/api/v1/products/{$product->id}/opportunities")
        ->assertForbidden();
});
