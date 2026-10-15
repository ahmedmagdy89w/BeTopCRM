<?php

use App\Models\Product;
use App\Models\ProductCategory;

// ── CRUD (happy path) ────────────────────────────────────────────────────────

test('admin can list product categories', function () {
    $admin = adminUser();
    ProductCategory::factory(3)->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson('/api/v1/product-categories')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('admin can create a product category', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/product-categories', ['name' => 'Software'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Software');

    $this->assertDatabaseHas('product_categories', ['name' => 'Software']);
});

test('admin can create a nested category', function () {
    $admin = adminUser();
    $parent = ProductCategory::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson('/api/v1/product-categories', [
            'name' => 'Sub-category',
            'parent_id' => $parent->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.parent_id', $parent->id);
});

test('admin can update a product category', function () {
    $admin = adminUser();
    $category = ProductCategory::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->putJson("/api/v1/product-categories/{$category->id}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed');
});

test('admin can update a product category without changing its name', function () {
    $admin = adminUser();
    $category = ProductCategory::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->putJson("/api/v1/product-categories/{$category->id}", ['name' => $category->name])
        ->assertOk()
        ->assertJsonPath('data.name', $category->name);
});

test('admin can delete a product category', function () {
    $admin = adminUser();
    $category = ProductCategory::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/product-categories/{$category->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('product_categories', ['id' => $category->id]);
});

test('cannot delete category that has sub-categories and returns translated error', function () {
    $admin = adminUser();
    $parent = ProductCategory::factory()->create(['created_by' => $admin->id]);
    ProductCategory::factory()->create(['created_by' => $admin->id, 'parent_id' => $parent->id]);

    // In English
    $this->actingAs($admin)
        ->withHeader('Accept-Language', 'en')
        ->deleteJson("/api/v1/product-categories/{$parent->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category'])
        ->assertJsonPath('errors.category.0', 'Cannot delete a category that has Products or sub-categories assigned to it.');

    // In Arabic
    $this->actingAs($admin)
        ->withHeader('Accept-Language', 'ar')
        ->deleteJson("/api/v1/product-categories/{$parent->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category'])
        ->assertJsonPath('errors.category.0', 'لا يمكن حذف فئة مرتبطة بـ المنتجات أو فئات فرعية.');
});

test('cannot delete category that has active products', function () {
    $admin = adminUser();
    $category = ProductCategory::factory()->create(['created_by' => $admin->id]);
    Product::factory()->create(['created_by' => $admin->id, 'category_id' => $category->id, 'is_active' => true]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/product-categories/{$category->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category']);
});

test('can delete category if all assigned products are soft deleted (inactive)', function () {
    $admin = adminUser();
    $category = ProductCategory::factory()->create(['created_by' => $admin->id]);
    $product = Product::factory()->inactive()->create(['created_by' => $admin->id, 'category_id' => $category->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/product-categories/{$category->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('product_categories', ['id' => $category->id]);
    expect($product->fresh()->category_id)->toBeNull();
});

// ── Tree endpoint ────────────────────────────────────────────────────────────

test('tree endpoint returns hierarchical categories', function () {
    $admin = adminUser();
    $parent = ProductCategory::factory()->create(['created_by' => $admin->id, 'parent_id' => null]);
    $child = ProductCategory::factory()->create(['created_by' => $admin->id, 'parent_id' => $parent->id]);

    $response = $this->actingAs($admin)
        ->getJson('/api/v1/product-categories/tree')
        ->assertOk();

    $data = $response->json('data');
    $parentNode = collect($data)->firstWhere('id', $parent->id);

    expect($parentNode)->not->toBeNull();
    expect($parentNode['children'])->not->toBeEmpty();
    expect(collect($parentNode['children'])->pluck('id')->toArray())->toContain($child->id);
});

// ── Reorder ──────────────────────────────────────────────────────────────────

test('admin can reorder product categories', function () {
    $admin = adminUser();
    $first = ProductCategory::factory()->create(['created_by' => $admin->id, 'sort_order' => 1]);
    $second = ProductCategory::factory()->create(['created_by' => $admin->id, 'sort_order' => 2]);

    $this->actingAs($admin)
        ->postJson('/api/v1/product-categories/reorder', [
            'categories' => [
                ['id' => $first->id,  'sort_order' => 2],
                ['id' => $second->id, 'sort_order' => 1],
            ],
        ])
        ->assertOk();

    expect($first->fresh()->sort_order)->toBe(2);
    expect($second->fresh()->sort_order)->toBe(1);
});

// ── Authorization ────────────────────────────────────────────────────────────

test('manager can view product categories', function () {
    $admin = adminUser();
    $manager = managerUser();
    ProductCategory::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($manager)
        ->getJson('/api/v1/product-categories')
        ->assertOk();
});

test('sales_rep cannot create product categories', function () {
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->postJson('/api/v1/product-categories', ['name' => 'Rep Category'])
        ->assertForbidden();
});

test('unauthenticated request on product categories returns 401', function () {
    $this->getJson('/api/v1/product-categories')->assertUnauthorized();
});

// ── Validation ───────────────────────────────────────────────────────────────

test('create product category requires name', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/product-categories', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});
