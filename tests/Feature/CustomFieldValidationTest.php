<?php

use App\Models\Customer;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldGroup;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\CustomFieldEntityRegistry;
use App\Services\CustomFieldService;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| ruleFor(): one place decides how each of the 13 types is validated
|--------------------------------------------------------------------------
*/

test('each supported type maps to a validation rule', function (string $type, string $expected) {
    expect(app(CustomFieldService::class)->ruleFor($type))->toContain($expected);
})->with([
    ['text', 'string'],
    ['textarea', 'string'],
    ['number', 'numeric'],
    ['decimal', 'numeric'],
    ['date', 'date_format:Y-m-d'],
    ['datetime', 'date'],
    ['select', 'string'],
    ['multi_select', 'array'],
    ['boolean', 'boolean'],
    ['color', 'string'],
    ['url', 'url'],
    ['email', 'email'],
    ['phone', 'string'],
]);

test('ruleFor accepts a definition or a bare type string', function () {
    $service = app(CustomFieldService::class);
    $definition = new CustomFieldDefinition(['type' => 'email']);

    expect($service->ruleFor($definition))->toBe($service->ruleFor('email'));
});

/*
|--------------------------------------------------------------------------
| Value rules
|--------------------------------------------------------------------------
*/

test('optional fields are nullable and required fields are required', function () {
    $group = CustomFieldGroup::factory()->forEntity('product')->create();
    CustomFieldDefinition::factory()->create(['group_id' => $group->id, 'name' => 'optional_one']);
    CustomFieldDefinition::factory()->create(['group_id' => $group->id, 'name' => 'required_one', 'is_required' => true]);

    $rules = app(CustomFieldService::class)->buildValueRules('product');

    expect($rules['custom_fields.optional_one'])->toContain('nullable')
        ->and($rules['custom_fields.optional_one'])->not->toContain('required')
        ->and($rules['custom_fields.required_one'])->toContain('required')
        ->and($rules['custom_fields.required_one'])->not->toContain('nullable');
});

test('a multi select validates every selected option against the definition options', function () {
    $group = CustomFieldGroup::factory()->forEntity('product')->create();
    CustomFieldDefinition::factory()->multiSelect(['Small', 'Large'])->create(['group_id' => $group->id, 'name' => 'sizes']);

    $rules = app(CustomFieldService::class)->buildValueRules('product');

    expect($rules)->toHaveKey('custom_fields.sizes.*')
        ->and($rules['custom_fields.sizes'])->toContain('array');
});

test('category value rules are keyed by the category values column', function () {
    $category = ProductCategory::factory()->create();
    $group = CustomFieldGroup::factory()->forCategory($category->id)->create();
    CustomFieldDefinition::factory()->create(['group_id' => $group->id, 'name' => 'aisle', 'is_required' => true]);

    $rules = app(CustomFieldService::class)->buildCategoryValueRules('product', $category->id);

    expect($rules)->toHaveKey('category_custom_fields.aisle')
        ->and($rules['category_custom_fields.aisle'])->toContain('required');
});

test('a category with no group yields no category rules', function () {
    $category = ProductCategory::factory()->create();

    expect(app(CustomFieldService::class)->buildCategoryValueRules('product', $category->id))->toBe([]);
});

/*
|--------------------------------------------------------------------------
| Cleaned values
|--------------------------------------------------------------------------
*/

test('values for fields that do not exist are dropped', function () {
    $group = CustomFieldGroup::factory()->forEntity('product')->create();
    CustomFieldDefinition::factory()->create(['group_id' => $group->id, 'name' => 'known']);

    $cleaned = app(CustomFieldService::class)->validateValues('product', [
        'known' => 'yes',
        'invented' => 'nope',
    ]);

    expect($cleaned)->toBe(['known' => 'yes']);
});

test('an empty value for an optional field is dropped rather than stored blank', function () {
    $group = CustomFieldGroup::factory()->forEntity('product')->create();
    CustomFieldDefinition::factory()->create(['group_id' => $group->id, 'name' => 'note']);

    $service = app(CustomFieldService::class);

    expect($service->validateValues('product', ['note' => '']))->toBe([])
        ->and($service->validateValues('product', ['note' => null]))->toBe([]);
});

test('a required field rejects a blank value', function () {
    $group = CustomFieldGroup::factory()->forEntity('product')->create();
    CustomFieldDefinition::factory()->required()->create(['group_id' => $group->id, 'name' => 'needed']);

    app(CustomFieldService::class)->validateValues('product', ['needed' => '']);
})->throws(ValidationException::class);

test('a required field rejects a null value', function () {
    $group = CustomFieldGroup::factory()->forEntity('product')->create();
    CustomFieldDefinition::factory()->required()->create(['group_id' => $group->id, 'name' => 'needed']);

    app(CustomFieldService::class)->validateValues('product', ['needed' => null]);
})->throws(ValidationException::class);

test('a required field rejects a missing key', function () {
    $group = CustomFieldGroup::factory()->forEntity('product')->create();
    CustomFieldDefinition::factory()->required()->create(['group_id' => $group->id, 'name' => 'needed']);

    app(CustomFieldService::class)->validateValues('product', []);
})->throws(ValidationException::class);

test('a required category field is only enforced for the category that declares it', function () {
    $strict = ProductCategory::factory()->create();
    $lenient = ProductCategory::factory()->create();

    $group = CustomFieldGroup::factory()->forCategory($strict->id)->create();
    CustomFieldDefinition::factory()->required()->create(['group_id' => $group->id, 'name' => 'hazard']);

    $service = app(CustomFieldService::class);

    expect($service->validateValues('product', [], CustomFieldEntityRegistry::SCOPE_CATEGORY, $lenient->id))->toBe([]);

    $service->validateValues('product', [], CustomFieldEntityRegistry::SCOPE_CATEGORY, $strict->id);
})->throws(ValidationException::class);

test('a model field and a category field of the same name are cleaned independently', function () {
    $category = ProductCategory::factory()->create();

    $modelGroup = CustomFieldGroup::factory()->forEntity('product')->create();
    CustomFieldDefinition::factory()->create(['group_id' => $modelGroup->id, 'name' => 'size']);

    $categoryGroup = CustomFieldGroup::factory()->forCategory($category->id)->create();
    CustomFieldDefinition::factory()->create(['group_id' => $categoryGroup->id, 'name' => 'size']);

    $service = app(CustomFieldService::class);

    expect($service->validateValues('product', ['size' => 'large']))
        ->toBe(['size' => 'large']);

    expect($service->validateValues('product', ['size' => 'oversized'], CustomFieldEntityRegistry::SCOPE_CATEGORY, $category->id))
        ->toBe(['size' => 'oversized']);
});

/*
|--------------------------------------------------------------------------
| Group lifecycle
|--------------------------------------------------------------------------
*/

test('the entity group is created on demand and then reused', function () {
    $user = adminUser();
    $service = app(CustomFieldService::class);

    $first = $service->entityGroup('product', $user->id);
    $second = $service->entityGroup('product', $user->id);

    expect($second->id)->toBe($first->id)
        ->and(CustomFieldGroup::query()->where('entity_type', 'product')->count())->toBe(1);
});

test('creating a definition for an entity with no group yet succeeds', function () {
    $admin = adminUser();

    $this->actingAs($admin)->postJson('/api/v1/custom-fields/opportunity', [
        'name' => 'first_field',
        'label' => 'First',
        'type' => 'text',
    ])->assertCreated();

    expect(CustomFieldGroup::query()->where('entity_type', 'opportunity')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Type validation on the product endpoint
|--------------------------------------------------------------------------
*/

test('a product rejects a value that violates its field type', function () {
    $admin = adminUser();

    $this->actingAs($admin)->postJson('/api/v1/custom-fields/product', [
        'name' => 'weight', 'label' => 'Weight', 'type' => 'number',
    ])->assertCreated();

    $this->actingAs($admin)->postJson('/api/v1/products', [
        'name' => 'Widget',
        'sku' => 'W-1',
        'unit_price' => 10,
        'custom_fields' => ['weight' => 'not-a-number'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['custom_fields.weight']);
});

test('a product rejects a select value outside the declared options', function () {
    $admin = adminUser();

    $this->actingAs($admin)->postJson('/api/v1/custom-fields/product', [
        'name' => 'finish', 'label' => 'Finish', 'type' => 'select', 'options' => ['Matte', 'Gloss'],
    ])->assertCreated();

    $this->actingAs($admin)->postJson('/api/v1/products', [
        'name' => 'Widget',
        'sku' => 'W-2',
        'unit_price' => 10,
        'custom_fields' => ['finish' => 'Sparkly'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['custom_fields.finish']);
});

test('a product rejects a multi select containing a value outside the declared options', function () {
    $admin = adminUser();

    $this->actingAs($admin)->postJson('/api/v1/custom-fields/product', [
        'name' => 'sizes', 'label' => 'Sizes', 'type' => 'multi_select', 'options' => ['Small', 'Large'],
    ])->assertCreated();

    $this->actingAs($admin)->postJson('/api/v1/products', [
        'name' => 'Widget',
        'sku' => 'W-3',
        'unit_price' => 10,
        'custom_fields' => ['sizes' => ['Small', 'Enormous']],
    ])->assertUnprocessable()->assertJsonValidationErrors(['custom_fields.sizes.1']);
});

test('a product requires a required custom field', function () {
    $admin = adminUser();

    $this->actingAs($admin)->postJson('/api/v1/custom-fields/product', [
        'name' => 'origin', 'label' => 'Origin', 'type' => 'text', 'is_required' => true,
    ])->assertCreated();

    $this->actingAs($admin)->postJson('/api/v1/products', [
        'name' => 'Widget',
        'sku' => 'W-4',
        'unit_price' => 10,
        'custom_fields' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors(['custom_fields.origin']);
});

test('a product without a category is not asked for that category required fields', function () {
    $admin = adminUser();

    $category = ProductCategory::factory()->create();
    $group = CustomFieldGroup::factory()->forCategory($category->id)->create();
    CustomFieldDefinition::factory()->required()->create(['group_id' => $group->id, 'name' => 'hazard']);

    $this->actingAs($admin)->postJson('/api/v1/products', [
        'name' => 'Widget',
        'sku' => 'W-5',
        'unit_price' => 10,
    ])->assertCreated();
});

test('a product in a category must satisfy that category required fields', function () {
    $admin = adminUser();

    $category = ProductCategory::factory()->create();
    $group = CustomFieldGroup::factory()->forCategory($category->id)->create();
    CustomFieldDefinition::factory()->required()->create(['group_id' => $group->id, 'name' => 'hazard']);

    $this->actingAs($admin)->postJson('/api/v1/products', [
        'name' => 'Widget',
        'sku' => 'W-6',
        'unit_price' => 10,
        'category_id' => $category->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['category_custom_fields.hazard']);
});

test('a customer enforces its own custom field rules', function () {
    $admin = adminUser();

    $this->actingAs($admin)->postJson('/api/v1/custom-fields/customer', [
        'name' => 'score', 'label' => 'Score', 'type' => 'number',
    ])->assertCreated();

    $this->actingAs($admin)->postJson('/api/v1/customers', [
        'type' => 'individual',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'custom_fields' => ['score' => 'high'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['custom_fields.score']);
});

test('partial entity updates do not require custom fields that were not submitted', function () {
    $admin = adminUser();
    $customerGroup = CustomFieldGroup::factory()->forEntity('customer')->create();
    CustomFieldDefinition::factory()->required()->create([
        'group_id' => $customerGroup->id,
        'name' => 'account_code',
    ]);
    $customer = Customer::factory()->create([
        'created_by' => $admin->id,
        'custom_fields' => ['account_code' => 'AC-1'],
    ]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}", ['company_name' => 'Updated Name'])
        ->assertOk();
});

test('submitted custom_fields still enforce required values on an entity update', function () {
    $admin = adminUser();
    $group = CustomFieldGroup::factory()->forEntity('customer')->create();
    CustomFieldDefinition::factory()->required()->create([
        'group_id' => $group->id,
        'name' => 'account_code',
    ]);
    $customer = Customer::factory()->create([
        'created_by' => $admin->id,
        'custom_fields' => ['account_code' => 'AC-1'],
    ]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}", ['custom_fields' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['custom_fields.account_code']);
});

test('a category change is not blocked by a newly required field when category values are omitted', function () {
    $admin = adminUser();
    $previousCategory = ProductCategory::factory()->create();
    $nextCategory = ProductCategory::factory()->create();
    $nextGroup = CustomFieldGroup::factory()->forCategory($nextCategory->id)->create();
    CustomFieldDefinition::factory()->required()->create([
        'group_id' => $nextGroup->id,
        'name' => 'care_label',
    ]);
    $product = Product::factory()->create([
        'category_id' => $previousCategory->id,
        'category_custom_fields' => ['old_field' => 'stored'],
    ]);

    $this->actingAs($admin)
        ->putJson("/api/v1/products/{$product->id}", ['category_id' => $nextCategory->id])
        ->assertOk();

    expect($product->fresh()->category_custom_fields)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Deleting a definition cleans up stored values
|--------------------------------------------------------------------------
*/

test('deleting a category scoped definition removes the key from product rows', function () {
    $admin = adminUser();
    $category = ProductCategory::factory()->create();

    $group = CustomFieldGroup::factory()->forCategory($category->id)->create();
    $definition = CustomFieldDefinition::factory()->create(['group_id' => $group->id, 'name' => 'aisle']);

    $product = Product::factory()->create([
        'category_id' => $category->id,
        'category_custom_fields' => ['aisle' => 'A3'],
    ]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/custom-fields/definitions/{$definition->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('custom_field_definitions', ['id' => $definition->id]);

    expect($product->fresh()->category_custom_fields)->toBe([]);
});
