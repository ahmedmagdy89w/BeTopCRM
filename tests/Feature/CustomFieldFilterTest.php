<?php

use App\Filters\CustomFieldFilter;
use App\Filters\ProductFilter;
use App\Models\Customer;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldGroup;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\CustomFieldEntityRegistry;
use App\Services\CustomFieldService;
use App\Services\OpportunityService;

/**
 * Create a filterable model-scoped definition for products.
 */
function productField(string $name, string $type, array $options = []): CustomFieldDefinition
{
    $group = CustomFieldGroup::query()->where('entity_type', 'product')->first()
        ?? CustomFieldGroup::factory()->forEntity('product')->create();

    return CustomFieldDefinition::factory()
        ->type($type, $options ?: null)
        ->filterable()
        ->create(['group_id' => $group->id, 'name' => $name]);
}

/**
 * Create a filterable category-scoped definition on a category.
 */
function categoryField(ProductCategory $category, string $name, string $type = 'text'): CustomFieldDefinition
{
    $group = CustomFieldGroup::forCategory($category->id)->first()
        ?? CustomFieldGroup::factory()->forCategory($category->id)->create();

    return CustomFieldDefinition::factory()
        ->type($type)
        ->filterable()
        ->create(['group_id' => $group->id, 'name' => $name]);
}

function productNamesIn(array $filters): array
{
    return (new ProductFilter($filters))
        ->apply(Product::query())
        ->pluck('name')
        ->sort()
        ->values()
        ->all();
}

/*
|--------------------------------------------------------------------------
| Model scope (cf_)
|--------------------------------------------------------------------------
*/

test('a cf_ filter matches on an exact text value', function () {
    productField('material', 'text');

    Product::factory()->create(['name' => 'A', 'custom_fields' => ['material' => 'Cotton']]);
    Product::factory()->create(['name' => 'B', 'custom_fields' => ['material' => 'Wool']]);

    expect(productNamesIn(['cf_material' => 'Cotton']))->toBe(['A']);
});

test('a text filter matches a substring', function () {
    productField('material', 'text');

    Product::factory()->create(['name' => 'A', 'custom_fields' => ['material' => 'Organic Cotton']]);
    Product::factory()->create(['name' => 'B', 'custom_fields' => ['material' => 'Wool']]);

    expect(productNamesIn(['cf_material' => 'Cotton']))->toBe(['A']);
});

test('a cf_ filter can combine with other product filters', function () {
    productField('material', 'text');
    $category = ProductCategory::factory()->create();

    Product::factory()->create(['name' => 'A', 'category_id' => $category->id, 'custom_fields' => ['material' => 'Cotton']]);
    Product::factory()->create(['name' => 'B', 'category_id' => $category->id, 'custom_fields' => ['material' => 'Wool']]);

    expect(productNamesIn(['cf_material' => 'Cotton', 'category_id' => $category->id]))->toBe(['A']);
});

test('a numeric cf_ filter only matches when the value is numeric', function () {
    productField('weight', 'number');

    Product::factory()->create(['name' => 'A', 'custom_fields' => ['weight' => 5]]);
    Product::factory()->create(['name' => 'B', 'custom_fields' => ['weight' => 9]]);

    expect(productNamesIn(['cf_weight' => 9]))->toBe(['B']);
});

test('a non numeric value for a number field is ignored rather than erroring', function () {
    productField('weight', 'number');

    Product::factory()->create(['name' => 'A', 'custom_fields' => ['weight' => 5]]);
    Product::factory()->create(['name' => 'B', 'custom_fields' => ['weight' => 9]]);

    expect(productNamesIn(['cf_weight' => 'heavy']))->toBe(['A', 'B']);
});

test('a multi select filter matches a product containing any of the selected values', function () {
    productField('sizes', 'multi_select', ['Small', 'Large']);

    Product::factory()->create(['name' => 'A', 'custom_fields' => ['sizes' => ['Small']]]);
    Product::factory()->create(['name' => 'B', 'custom_fields' => ['sizes' => ['Large']]]);
    Product::factory()->create(['name' => 'C', 'custom_fields' => ['sizes' => ['Small', 'Large']]]);

    expect(productNamesIn(['cf_sizes' => ['Small']]))->toBe(['A', 'C'])
        ->and(productNamesIn(['cf_sizes' => ['Small', 'Large']]))->toBe(['A', 'B', 'C'])
        ->and(productNamesIn(['cf_sizes' => 'Large']))->toBe(['B', 'C'])
        ->and(productNamesIn(['cf_sizes' => 'Small, Large']))->toBe(['A', 'B', 'C']);
});

test('a boolean cf_ filter matches true and false', function () {
    productField('gift_wrap', 'boolean');

    Product::factory()->create(['name' => 'A', 'custom_fields' => ['gift_wrap' => true]]);
    Product::factory()->create(['name' => 'B', 'custom_fields' => ['gift_wrap' => false]]);

    expect(productNamesIn(['cf_gift_wrap' => '1']))->toBe(['A'])
        ->and(productNamesIn(['cf_gift_wrap' => '0']))->toBe(['B']);
});

/*
|--------------------------------------------------------------------------
| Category scope (ccf_)
|--------------------------------------------------------------------------
*/

test('a ccf_ filter searches the category values column', function () {
    $category = ProductCategory::factory()->create();
    categoryField($category, 'aisle');

    Product::factory()->create(['name' => 'A', 'category_id' => $category->id, 'category_custom_fields' => ['aisle' => 'A3']]);
    Product::factory()->create(['name' => 'B', 'category_id' => $category->id, 'category_custom_fields' => ['aisle' => 'B7']]);

    expect(productNamesIn(['ccf_aisle' => 'A3']))->toBe(['A']);
});

test('a ccf_ field declared by only one category still filters across the whole list', function () {
    $welding = ProductCategory::factory()->create();
    $sewing = ProductCategory::factory()->create();

    categoryField($welding, 'aisle');

    Product::factory()->create(['name' => 'A', 'category_id' => $welding->id, 'category_custom_fields' => ['aisle' => 'W1']]);
    Product::factory()->create(['name' => 'B', 'category_id' => $sewing->id]);

    expect(productNamesIn(['ccf_aisle' => 'W1']))->toBe(['A']);
});

test('cf_ and ccf_ read from separate columns and do not cross over', function () {
    $category = ProductCategory::factory()->create();
    productField('size', 'text');
    categoryField($category, 'size', 'text');

    Product::factory()->create([
        'name' => 'A',
        'category_id' => $category->id,
        'custom_fields' => ['size' => 'model'],
        'category_custom_fields' => ['size' => 'category'],
    ]);

    expect(productNamesIn(['cf_size' => 'model']))->toBe(['A'])
        ->and(productNamesIn(['cf_size' => 'category']))->toBe([])
        ->and(productNamesIn(['ccf_size' => 'category']))->toBe(['A'])
        ->and(productNamesIn(['ccf_size' => 'model']))->toBe([]);
});

/*
|--------------------------------------------------------------------------
| is_filterable is honoured
|--------------------------------------------------------------------------
*/

test('a cf_ param for a field that is not filterable is ignored', function () {
    $group = CustomFieldGroup::factory()->forEntity('product')->create();
    CustomFieldDefinition::factory()->create([
        'group_id' => $group->id,
        'name' => 'internal_note',
        'is_filterable' => false,
    ]);

    Product::factory()->create(['name' => 'A', 'custom_fields' => ['internal_note' => 'secret']]);
    Product::factory()->create(['name' => 'B', 'custom_fields' => ['internal_note' => 'secret']]);

    expect(productNamesIn(['cf_internal_note' => 'secret']))->toBe(['A', 'B']);
});

test('a cf_ param for a field that does not exist is ignored', function () {
    Product::factory()->create(['name' => 'A', 'custom_fields' => ['material' => 'Cotton']]);

    expect(productNamesIn(['cf_never_defined' => 'x']))->toBe(['A']);
});

test('a ccf_ param for a field that is not filterable is ignored', function () {
    $category = ProductCategory::factory()->create();

    $group = CustomFieldGroup::forCategory($category->id)->first()
        ?? CustomFieldGroup::factory()->forCategory($category->id)->create();

    CustomFieldDefinition::factory()->create([
        'group_id' => $group->id,
        'name' => 'restocked_on',
        'is_filterable' => false,
    ]);

    Product::factory()->create([
        'name' => 'A',
        'category_id' => $category->id,
        'category_custom_fields' => ['restocked_on' => '2026-01-01'],
    ]);

    expect(productNamesIn(['ccf_restocked_on' => '2026-01-01']))->toBe(['A']);
});

/*
|--------------------------------------------------------------------------
| The filter is safe on a model that has no custom fields
|--------------------------------------------------------------------------
*/

test('the filter is a no-op for a model that is not in the registry', function () {
    $definition = CustomFieldDefinition::factory()->create(['name' => 'material']);

    $ids = (new CustomFieldFilter(
        ['cf_material' => 'Cotton'],
        app(CustomFieldService::class),
        app(CustomFieldEntityRegistry::class),
    ))->apply(Opportunity::query())->pluck('id')->all();

    expect($ids)->toBe(Opportunity::query()->pluck('id')->all())
        ->and($definition)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| sort_by / sort_dir
|--------------------------------------------------------------------------
*/

test('products sort by the requested column and direction', function () {
    Product::factory()->create(['name' => 'Bravo']);
    Product::factory()->create(['name' => 'Alpha']);
    Product::factory()->create(['name' => 'Charlie']);

    expect(productNamesIn(['sort_by' => 'name', 'sort_dir' => 'asc']))->toBe(['Alpha', 'Bravo', 'Charlie']);
});

test('an unknown sort column falls back to the default instead of reaching the query', function () {
    Product::factory()->create(['name' => 'Bravo']);
    Product::factory()->create(['name' => 'Alpha']);

    expect(productNamesIn(['sort_by' => 'name; drop table products', 'sort_dir' => 'asc']))->toBe(['Alpha', 'Bravo']);
});

test('an unknown sort direction falls back to the default direction', function () {
    Product::factory()->create(['name' => 'Bravo']);
    Product::factory()->create(['name' => 'Alpha']);

    expect(productNamesIn(['sort_by' => 'name', 'sort_dir' => 'sideways']))->toBe(['Alpha', 'Bravo']);
});

test('opportunities keep their existing sort behaviour through the new filter', function () {
    $customer = Customer::factory()->create();
    $user = adminUser();

    Opportunity::factory()->create(['customer_id' => $customer->id, 'title' => 'Newer', 'amount' => 100, 'stage' => 'prospecting', 'created_at' => now()]);
    Opportunity::factory()->create(['customer_id' => $customer->id, 'title' => 'Older', 'amount' => 500, 'stage' => 'won', 'created_at' => now()->subDays(5)]);

    $defaultOrder = app(OpportunityService::class)
        ->list($customer->id)->pluck('title')->all();

    expect($defaultOrder)->toBe(['Newer', 'Older']);

    $byAmount = app(OpportunityService::class)
        ->list($customer->id, ['sort_by' => 'amount', 'sort_dir' => 'desc'])->pluck('title')->all();

    expect($byAmount)->toBe(['Older', 'Newer']);
});

test('opportunities still honour stage, amount and search filters', function () {
    $customer = Customer::factory()->create(['company_name' => 'Acme Corp']);

    Opportunity::factory()->create(['customer_id' => $customer->id, 'title' => 'Big opportunity', 'amount' => 900, 'stage' => 'won']);
    Opportunity::factory()->create(['customer_id' => $customer->id, 'title' => 'Small opportunity', 'amount' => 100, 'stage' => 'prospecting']);

    $service = app(OpportunityService::class);

    expect($service->list($customer->id, ['stage' => 'won'])->pluck('title')->all())->toBe(['Big opportunity'])
        ->and($service->list($customer->id, ['amount_min' => 500])->pluck('title')->all())->toBe(['Big opportunity'])
        ->and($service->list($customer->id, ['search' => 'Small'])->pluck('title')->all())->toBe(['Small opportunity'])
        ->and($service->list($customer->id, ['search' => 'Acme'])->pluck('title')->count())->toBe(2);
});
