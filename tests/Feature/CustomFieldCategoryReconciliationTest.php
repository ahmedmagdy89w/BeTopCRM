<?php

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldGroup;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\CustomFieldService;
use App\Services\ProductService;

/**
 * @return array{0: ProductCategory, 1: ProductCategory}
 */
function twoCategoriesWithOwnFields(): array
{
    $pants = ProductCategory::factory()->create(['name' => 'Pants']);
    $shirts = ProductCategory::factory()->create(['name' => 'T-Shirts']);

    $pantsGroup = CustomFieldGroup::factory()->forCategory($pants->id)->create();
    CustomFieldDefinition::factory()->create(['group_id' => $pantsGroup->id, 'name' => 'size']);
    CustomFieldDefinition::factory()->type('number')->create(['group_id' => $pantsGroup->id, 'name' => 'inseam']);

    $shirtsGroup = CustomFieldGroup::factory()->forCategory($shirts->id)->create();
    CustomFieldDefinition::factory()->create(['group_id' => $shirtsGroup->id, 'name' => 'size']);

    return [$pants, $shirts];
}

/*
|--------------------------------------------------------------------------
| reconcileCategoryValues()
|--------------------------------------------------------------------------
*/

test('reconcile keeps only keys the target category declares', function () {
    [, $shirts] = twoCategoriesWithOwnFields();

    $reconciled = app(CustomFieldService::class)->reconcileCategoryValues(
        ['size' => 'M', 'inseam' => 32],
        $shirts->id,
    );

    expect($reconciled)->toBe(['size' => 'M']);
});

test('reconcile against a category with no group yields an empty map', function () {
    $category = ProductCategory::factory()->create();

    expect(app(CustomFieldService::class)->reconcileCategoryValues(['size' => 'M'], $category->id))->toBe([]);
});

test('reconcile against no category yields an empty map', function () {
    expect(app(CustomFieldService::class)->reconcileCategoryValues(['size' => 'M'], null))->toBe([]);
});

test('reconcile does not enforce requiredness so re-categorising cannot fail validation', function () {
    [, $shirts] = twoCategoriesWithOwnFields();

    $shirtsGroup = CustomFieldGroup::forCategory($shirts->id)->first();
    CustomFieldDefinition::factory()->required()->create([
        'group_id' => $shirtsGroup->id,
        'name' => 'care_label',
    ]);

    expect(app(CustomFieldService::class)->reconcileCategoryValues([], $shirts->id))->toBe([]);
});

/*
|--------------------------------------------------------------------------
| The documented behaviour: Pants -> T-Shirts keeps size, drops inseam
|--------------------------------------------------------------------------
*/

test('moving a product to a category that still declares a field keeps that value', function () {
    $admin = adminUser();
    [$pants, $shirts] = twoCategoriesWithOwnFields();

    $response = $this->actingAs($admin)->postJson('/api/v1/products', [
        'name' => 'Chinos',
        'sku' => 'CH-1',
        'unit_price' => 40,
        'category_id' => $pants->id,
        'category_custom_fields' => ['size' => '32', 'inseam' => 32],
    ])->assertCreated();

    $id = $response->json('data.id');

    $this->actingAs($admin)->putJson("/api/v1/products/{$id}", [
        'category_id' => $shirts->id,
    ])->assertOk();

    $this->assertDatabaseHas('products', [
        'id' => $id,
    ]);

    expect(Product::find($id)->category_custom_fields)->toBe(['size' => '32']);
});

test('moving a product to a category that does not declare a field drops that value', function () {
    $admin = adminUser();
    [$pants, $shirts] = twoCategoriesWithOwnFields();

    $id = $this->actingAs($admin)->postJson('/api/v1/products', [
        'name' => 'Chinos',
        'sku' => 'CH-2',
        'unit_price' => 40,
        'category_id' => $pants->id,
        'category_custom_fields' => ['size' => '32', 'inseam' => 32],
    ])->assertCreated()->json('data.id');

    $this->actingAs($admin)->putJson("/api/v1/products/{$id}", [
        'category_id' => $shirts->id,
    ])->assertOk();

    expect(Product::find($id)->category_custom_fields)->not->toHaveKey('inseam');
});

test('moving a product to a category with no group at all clears the values', function () {
    $admin = adminUser();
    [$pants] = twoCategoriesWithOwnFields();
    $bare = ProductCategory::factory()->create();

    $id = $this->actingAs($admin)->postJson('/api/v1/products', [
        'name' => 'Chinos',
        'sku' => 'CH-3',
        'unit_price' => 40,
        'category_id' => $pants->id,
        'category_custom_fields' => ['size' => '32'],
    ])->assertCreated()->json('data.id');

    $this->actingAs($admin)->putJson("/api/v1/products/{$id}", [
        'category_id' => $bare->id,
    ])->assertOk();

    expect(Product::find($id)->category_custom_fields)->toBeNull();
});

test('clearing a product category clears the category scoped values', function () {
    $admin = adminUser();
    [$pants] = twoCategoriesWithOwnFields();

    $id = $this->actingAs($admin)->postJson('/api/v1/products', [
        'name' => 'Chinos',
        'sku' => 'CH-4',
        'unit_price' => 40,
        'category_id' => $pants->id,
        'category_custom_fields' => ['size' => '32'],
    ])->assertCreated()->json('data.id');

    $this->actingAs($admin)->putJson("/api/v1/products/{$id}", [
        'category_id' => null,
    ])->assertOk();

    expect(Product::find($id)->category_custom_fields)->toBeNull();
});

test('a category change also accepts fresh values for the new category', function () {
    $admin = adminUser();
    [$pants, $shirts] = twoCategoriesWithOwnFields();

    $id = $this->actingAs($admin)->postJson('/api/v1/products', [
        'name' => 'Chinos',
        'sku' => 'CH-5',
        'unit_price' => 40,
        'category_id' => $pants->id,
        'category_custom_fields' => ['size' => '32', 'inseam' => 32],
    ])->assertCreated()->json('data.id');

    $this->actingAs($admin)->putJson("/api/v1/products/{$id}", [
        'category_id' => $shirts->id,
        'category_custom_fields' => ['size' => 'L'],
    ])->assertOk();

    expect(Product::find($id)->category_custom_fields)->toBe(['size' => 'L']);
});

/*
|--------------------------------------------------------------------------
| Reconciliation must not fire on an unrelated partial update
|--------------------------------------------------------------------------
*/

test('a partial update that omits the category does not wipe stored values', function () {
    $admin = adminUser();
    [$pants] = twoCategoriesWithOwnFields();

    $id = $this->actingAs($admin)->postJson('/api/v1/products', [
        'name' => 'Chinos',
        'sku' => 'CH-6',
        'unit_price' => 40,
        'category_id' => $pants->id,
        'category_custom_fields' => ['size' => '32', 'inseam' => 32],
    ])->assertCreated()->json('data.id');

    $this->actingAs($admin)->putJson("/api/v1/products/{$id}", [
        'name' => 'Chinos Slim',
    ])->assertOk();

    expect(Product::find($id)->category_custom_fields)->toBe(['size' => '32', 'inseam' => 32]);
});

test('resubmitting the same category does not wipe stored values', function () {
    $admin = adminUser();
    [$pants] = twoCategoriesWithOwnFields();

    $id = $this->actingAs($admin)->postJson('/api/v1/products', [
        'name' => 'Chinos',
        'sku' => 'CH-7',
        'unit_price' => 40,
        'category_id' => $pants->id,
        'category_custom_fields' => ['size' => '32', 'inseam' => 32],
    ])->assertCreated()->json('data.id');

    $this->actingAs($admin)->putJson("/api/v1/products/{$id}", [
        'category_id' => $pants->id,
    ])->assertOk();

    expect(Product::find($id)->category_custom_fields)->toBe(['size' => '32', 'inseam' => 32]);
});

/*
|--------------------------------------------------------------------------
| Keys the category never declared are refused on write
|--------------------------------------------------------------------------
*/

test('creating a product cannot store a category value the category never declared', function () {
    $admin = adminUser();
    [$pants] = twoCategoriesWithOwnFields();

    $id = $this->actingAs($admin)->postJson('/api/v1/products', [
        'name' => 'Chinos',
        'sku' => 'CH-8',
        'unit_price' => 40,
        'category_id' => $pants->id,
        'category_custom_fields' => ['size' => '32', 'smuggled' => 'yes'],
    ])->assertCreated()->json('data.id');

    expect(Product::find($id)->category_custom_fields)->toBe(['size' => '32']);
});

test('the service is the authority even when a request bypasses the form request', function () {
    $admin = adminUser();
    [$pants] = twoCategoriesWithOwnFields();

    $product = app(ProductService::class)->create(
        [
            'name' => 'Direct',
            'sku' => 'CH-9',
            'unit_price' => 10,
            'category_id' => $pants->id,
            'category_custom_fields' => ['size' => '32', 'nope' => 1],
        ],
        $admin,
    );

    expect($product->category_custom_fields)->toBe(['size' => '32']);
});

test('model scoped values are untouched by a category change', function () {
    $admin = adminUser();
    [$pants, $shirts] = twoCategoriesWithOwnFields();

    $this->actingAs($admin)->postJson('/api/v1/custom-fields/product', [
        'name' => 'size', 'label' => 'Model Size', 'type' => 'text',
    ])->assertCreated();

    $id = $this->actingAs($admin)->postJson('/api/v1/products', [
        'name' => 'Chinos',
        'sku' => 'CH-10',
        'unit_price' => 40,
        'category_id' => $pants->id,
        'custom_fields' => ['size' => 'model-level'],
        'category_custom_fields' => ['size' => '32', 'inseam' => 32],
    ])->assertCreated()->json('data.id');

    $this->actingAs($admin)->putJson("/api/v1/products/{$id}", [
        'category_id' => $shirts->id,
    ])->assertOk();

    $product = Product::find($id);

    expect($product->custom_fields)->toBe(['size' => 'model-level'])
        ->and($product->category_custom_fields)->toBe(['size' => '32']);
});
