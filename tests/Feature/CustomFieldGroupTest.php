<?php

use App\Models\Customer;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldGroup;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Traits\HasCustomFields;
use App\Services\CustomFieldEntityRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| Registry
|--------------------------------------------------------------------------
*/

test('registry exposes the supported entity types', function () {
    expect(app(CustomFieldEntityRegistry::class)->types())
        ->toBe(['product', 'customer', 'opportunity']);
});

test('only product supports category scoped custom fields', function () {
    $registry = app(CustomFieldEntityRegistry::class);

    expect($registry->hasCategoryScope('product'))->toBeTrue();

    foreach (['customer', 'opportunity'] as $entityType) {
        expect($registry->hasCategoryScope($entityType))->toBeFalse();
    }
});

test('registry resolves an entity type from a model class without per model boilerplate', function () {
    $registry = app(CustomFieldEntityRegistry::class);

    expect($registry->typeForModel(Product::class))->toBe('product')
        ->and($registry->typeForModel(Customer::class))->toBe('customer')
        ->and($registry->typeForModel(Opportunity::class))->toBe('opportunity')
        ->and($registry->typeForModel(Collection::class))->toBeNull();
});

test('registry derives the scope key that backs the one group per target index', function () {
    $registry = app(CustomFieldEntityRegistry::class);

    expect($registry->scopeKey())->toBe('entity')
        ->and($registry->scopeKey(CustomFieldEntityRegistry::SCOPE_CATEGORY, 7))->toBe('category:7');
});

test('registry rejects an unknown entity type', function () {
    app(CustomFieldEntityRegistry::class)->guard('invoice');
})->throws(ValidationException::class);

test('registry rejects a category scope for an entity that has no categories', function () {
    app(CustomFieldEntityRegistry::class)->guard('customer', CustomFieldEntityRegistry::SCOPE_CATEGORY);
})->throws(ValidationException::class);

test('registry guards a valid category scope', function () {
    app(CustomFieldEntityRegistry::class)->guard('product', CustomFieldEntityRegistry::SCOPE_CATEGORY);
})->throwsNoExceptions();

/*
|--------------------------------------------------------------------------
| Schema
|--------------------------------------------------------------------------
*/

test('definitions no longer carry entity_type and are unique per group', function () {
    expect(Schema::hasColumn('custom_field_definitions', 'group_id'))->toBeTrue()
        ->and(Schema::hasColumn('custom_field_definitions', 'entity_type'))->toBeFalse();
});

test('products store model scope and category scope values in separate columns', function () {
    expect(Schema::hasColumn('products', 'custom_fields'))->toBeTrue()
        ->and(Schema::hasColumn('products', 'category_custom_fields'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Groups
|--------------------------------------------------------------------------
*/

test('a model may only have one entity scoped group', function () {
    CustomFieldGroup::factory()->forEntity('product', 'Product Fields')->create();

    CustomFieldGroup::factory()->forEntity('product', 'Another Set')->create();
})->throws(QueryException::class);

test('a category may only have one group', function () {
    $category = ProductCategory::factory()->create();

    CustomFieldGroup::factory()->forCategory($category->id)->create();

    CustomFieldGroup::factory()->forCategory($category->id)->create();
})->throws(QueryException::class);

test('each category has its own independent group', function () {
    $first = ProductCategory::factory()->create();
    $second = ProductCategory::factory()->create();

    CustomFieldGroup::factory()->forCategory($first->id)->create();
    $group = CustomFieldGroup::factory()->forCategory($second->id)->create();

    expect($group->scope_key)->toBe('category:'.$second->id)
        ->and(CustomFieldGroup::forCategory($first->id)->count())->toBe(1)
        ->and(CustomFieldGroup::forCategory($second->id)->count())->toBe(1);
});

test('saving a group derives scope_key so a partial write cannot bypass the index', function () {
    $group = CustomFieldGroup::factory()->forEntity('opportunity')->create(['scope_key' => 'tampered']);

    expect($group->fresh()->scope_key)->toBe('entity');
});

test('a group knows whether it is category scoped', function () {
    $category = ProductCategory::factory()->create();

    expect(CustomFieldGroup::factory()->forEntity('product')->create()->isCategoryScoped())->toBeFalse()
        ->and(CustomFieldGroup::factory()->forCategory($category->id)->create()->isCategoryScoped())->toBeTrue();
});

test('a group exposes its definitions in order', function () {
    $group = CustomFieldGroup::factory()->forEntity('product')->create();

    CustomFieldDefinition::factory()->create(['group_id' => $group->id, 'name' => 'second', 'sort_order' => 2]);
    CustomFieldDefinition::factory()->create(['group_id' => $group->id, 'name' => 'first', 'sort_order' => 1]);

    expect($group->definitions->pluck('name')->all())->toBe(['first', 'second']);
});

test('a definition name is unique within its group but free across groups', function () {
    $first = CustomFieldGroup::factory()->forEntity('product')->create();

    CustomFieldDefinition::factory()->create(['group_id' => $first->id, 'name' => 'size']);

    CustomFieldDefinition::factory()->create(['group_id' => $first->id, 'name' => 'size']);
})->throws(QueryException::class);

test('the same name may be reused in a different group', function () {
    $first = CustomFieldGroup::factory()->forEntity('product')->create();
    $second = CustomFieldGroup::factory()->forCategory(ProductCategory::factory()->create()->id)->create();

    CustomFieldDefinition::factory()->create(['group_id' => $first->id, 'name' => 'size']);
    CustomFieldDefinition::factory()->create(['group_id' => $second->id, 'name' => 'size']);

    expect(CustomFieldDefinition::count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Definitions
|--------------------------------------------------------------------------
*/

test('definitions support the full field type set', function () {
    expect(CustomFieldDefinition::TYPES)->toBe([
        'text', 'textarea', 'number', 'decimal', 'date', 'datetime',
        'select', 'multi_select', 'boolean', 'color', 'url', 'email', 'phone',
    ]);
});

test('only choice based types require options', function () {
    $definition = new CustomFieldDefinition(['type' => 'multi_select']);

    expect($definition->requiresOptions())->toBeTrue();

    $definition = new CustomFieldDefinition(['type' => 'text']);

    expect($definition->requiresOptions())->toBeFalse();
});

test('a definition reports its entity type through its group', function () {
    $group = CustomFieldGroup::factory()->forEntity('customer')->create();

    expect(CustomFieldDefinition::factory()->create(['group_id' => $group->id])->entityType())
        ->toBe('customer');
});

test('a definition resolves the values column for its own scope', function () {
    $registry = app(CustomFieldEntityRegistry::class);

    $entityScoped = CustomFieldDefinition::factory()->create([
        'group_id' => CustomFieldGroup::factory()->forEntity('product')->create()->id,
    ]);

    $categoryScoped = CustomFieldDefinition::factory()->create([
        'group_id' => CustomFieldGroup::factory()->forCategory(ProductCategory::factory()->create()->id)->create()->id,
    ]);

    expect($entityScoped->valuesColumn($registry))->toBe('custom_fields')
        ->and($categoryScoped->valuesColumn($registry))->toBe('category_custom_fields');
});

test('definitions can be selected for an entity type through their group', function () {
    $group = CustomFieldGroup::factory()->forEntity('opportunity')->create();

    CustomFieldDefinition::factory()->create(['group_id' => $group->id]);

    expect(CustomFieldDefinition::forEntity('opportunity')->count())->toBe(1)
        ->and(CustomFieldDefinition::forEntity('customer')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| HasCustomFields
|--------------------------------------------------------------------------
*/

test('every registered model resolves its own entity type', function (string $model, string $expected) {
    $instance = new $model;

    expect(in_array(HasCustomFields::class, class_uses_recursive($model), true))->toBeTrue()
        ->and($instance->customFieldEntityType())->toBe($expected);
})->with([
    [Product::class, 'product'],
    [Customer::class, 'customer'],
    [Opportunity::class, 'opportunity'],
]);

test('product casts both value columns to arrays', function () {
    $product = Product::factory()->create([
        'custom_fields' => ['size' => 'large'],
        'category_custom_fields' => ['size' => 'oversized'],
    ]);

    expect($product->custom_fields)->toBe(['size' => 'large'])
        ->and($product->category_custom_fields)->toBe(['size' => 'oversized']);
});

test('a same named model field and category field coexist without collision', function () {
    $product = Product::factory()->create([
        'custom_fields' => ['size' => 'large'],
        'category_custom_fields' => ['size' => 'oversized'],
    ]);

    expect($product->customFieldValues())->toBe(['size' => 'large'])
        ->and($product->customFieldCategoryValues())->toBe(['size' => 'oversized']);
});

test('an entity without categories reports no category id or values', function () {
    $customer = Customer::factory()->create(['custom_fields' => ['segment' => 'smb']]);

    expect($customer->customFieldCategoryId())->toBeNull()
        ->and($customer->customFieldCategoryValues())->toBe([])
        ->and($customer->customFieldCategoryDefinitions())->toBeEmpty();
});

test('a product resolves its category id and category scoped definitions', function () {
    $category = ProductCategory::factory()->create();
    $group = CustomFieldGroup::factory()->forCategory($category->id)->create();

    CustomFieldDefinition::factory()->create(['group_id' => $group->id, 'name' => 'aisle']);

    $product = Product::factory()->create(['category_id' => $category->id]);

    expect($product->customFieldCategoryId())->toBe($category->id)
        ->and($product->customFieldCategoryDefinitions()->pluck('name')->all())->toBe(['aisle']);
});

test('a product without a category has no category scoped definitions', function () {
    expect(Product::factory()->create()->customFieldCategoryDefinitions())->toBeEmpty();
});

test('category scoped values are not exposed for an entity that has no such column', function () {
    expect(Schema::hasColumn('customers', 'category_custom_fields'))->toBeFalse();
});
