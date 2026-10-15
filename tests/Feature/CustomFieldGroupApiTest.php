<?php

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldGroup;
use App\Models\Product;
use App\Models\ProductCategory;

test('admin can list custom field entities from the registry', function () {
    $this->actingAs(adminUser())
        ->getJson('/api/v1/custom-fields/entities')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonFragment([
            'entity_type' => 'product',
            'label' => 'products.title',
            'supports_categories' => true,
        ])
        ->assertJsonFragment([
            'entity_type' => 'customer',
            'label' => 'customers.title',
            'supports_categories' => false,
        ]);
});

test('entity definitions endpoint returns group and definitions without creating an empty group', function () {
    $this->actingAs(adminUser())
        ->getJson('/api/v1/custom-fields/product')
        ->assertOk()
        ->assertJsonPath('data.group', null)
        ->assertJsonPath('data.definitions', []);

    $this->assertDatabaseMissing('custom_field_groups', ['entity_type' => 'product']);
});

test('entity definitions endpoint returns the group and its definitions', function () {
    $group = CustomFieldGroup::factory()->forEntity('product', 'Product Specs')->create();
    CustomFieldDefinition::factory()->create([
        'group_id' => $group->id,
        'name' => 'finish',
        'label' => 'Finish',
    ]);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/custom-fields/product')
        ->assertOk()
        ->assertJsonPath('data.group.id', $group->id)
        ->assertJsonPath('data.group.name', 'Product Specs')
        ->assertJsonPath('data.definitions.0.name', 'finish')
        ->assertJsonPath('data.definitions.0.entity_type', 'product');
});

test('category definitions endpoint returns only that category group', function () {
    $category = ProductCategory::factory()->create();
    $group = CustomFieldGroup::factory()->forCategory($category->id, 'T-Shirt Fields')->create();
    CustomFieldDefinition::factory()->create(['group_id' => $group->id, 'name' => 'size']);

    $otherCategory = ProductCategory::factory()->create();
    $otherGroup = CustomFieldGroup::factory()->forCategory($otherCategory->id)->create();
    CustomFieldDefinition::factory()->create(['group_id' => $otherGroup->id, 'name' => 'inseam']);

    $this->actingAs(adminUser())
        ->getJson("/api/v1/custom-fields/product/categories/{$category->id}")
        ->assertOk()
        ->assertJsonPath('data.group.id', $group->id)
        ->assertJsonPath('data.definitions.0.name', 'size')
        ->assertJsonMissing(['name' => 'inseam']);
});

test('a category with no group does not inherit fields from its parent', function () {
    $parent = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->create(['parent_id' => $parent->id]);
    $parentGroup = CustomFieldGroup::factory()->forCategory($parent->id)->create();
    CustomFieldDefinition::factory()->create(['group_id' => $parentGroup->id, 'name' => 'parent_only']);

    $this->actingAs(adminUser())
        ->getJson("/api/v1/custom-fields/product/categories/{$child->id}")
        ->assertOk()
        ->assertJsonPath('data.group', null)
        ->assertJsonPath('data.definitions', []);
});

test('an admin can create a category scoped definition through the category endpoint', function () {
    $admin = adminUser();
    $category = ProductCategory::factory()->create();

    $response = $this->actingAs($admin)
        ->postJson("/api/v1/custom-fields/product/categories/{$category->id}/definitions", [
            'name' => 'care_label',
            'label' => 'Care label',
            'type' => 'select',
            'options' => ['Cold wash', 'Hand wash'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.scope', 'category')
        ->assertJsonPath('data.category_id', $category->id);

    $this->assertDatabaseHas('custom_field_definitions', [
        'id' => $response->json('data.id'),
        'name' => 'care_label',
    ]);

    $this->actingAs($admin)
        ->getJson("/api/v1/custom-fields/product/categories/{$category->id}")
        ->assertOk()
        ->assertJsonPath('data.definitions.0.name', 'care_label');
});

test('category scoped definitions can be updated and deleted through shared definition routes', function () {
    $admin = adminUser();
    $category = ProductCategory::factory()->create();

    $definitionId = $this->actingAs($admin)
        ->postJson("/api/v1/custom-fields/product/categories/{$category->id}/definitions", [
            'name' => 'care_label',
            'label' => 'Care label',
            'type' => 'text',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($admin)
        ->putJson("/api/v1/custom-fields/definitions/{$definitionId}", [
            'name' => 'washing_instructions',
            'label' => 'Washing instructions',
            'type' => 'textarea',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'washing_instructions')
        ->assertJsonPath('data.scope', 'category');

    $this->actingAs($admin)
        ->deleteJson("/api/v1/custom-fields/definitions/{$definitionId}")
        ->assertNoContent();

    $this->assertDatabaseMissing('custom_field_definitions', ['id' => $definitionId]);
});

test('choice field definitions require a nonempty options list', function () {
    $admin = adminUser();

    foreach (['select', 'multi_select'] as $type) {
        $this->actingAs($admin)
            ->postJson('/api/v1/custom-fields/product', [
                'name' => "{$type}_without_options",
                'label' => 'Missing options',
                'type' => $type,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['options']);
    }
});

test('an admin can rename and change a field type within its group', function () {
    $group = CustomFieldGroup::factory()->forEntity('product')->create();
    $definition = CustomFieldDefinition::factory()->create([
        'group_id' => $group->id,
        'name' => 'old_field',
        'type' => 'text',
    ]);

    $this->actingAs(adminUser())
        ->putJson("/api/v1/custom-fields/definitions/{$definition->id}", [
            'name' => 'new_field',
            'type' => 'decimal',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'new_field')
        ->assertJsonPath('data.type', 'decimal');
});

test('the entities route is not swallowed by the entity type route', function () {
    $this->actingAs(adminUser())
        ->getJson('/api/v1/custom-fields/entities')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('definition routes are not swallowed by the entity type route', function () {
    $admin = adminUser();
    $group = CustomFieldGroup::factory()->forEntity('customer')->create();
    $definition = CustomFieldDefinition::factory()->create(['group_id' => $group->id]);

    $this->actingAs($admin)
        ->getJson("/api/v1/custom-fields/definitions/{$definition->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $definition->id);
});

test('an unsupported entity type is rejected by the route constraint', function () {
    $this->actingAs(adminUser())
        ->getJson('/api/v1/custom-fields/banana')
        ->assertNotFound();
});

test('a category scope request for a model without category support is rejected', function () {
    $category = ProductCategory::factory()->create();

    $this->actingAs(adminUser())
        ->getJson("/api/v1/custom-fields/customer/categories/{$category->id}")
        ->assertUnprocessable();
});

test('an admin can create an opportunity scoped group', function () {
    $this->actingAs(adminUser())
        ->postJson('/api/v1/custom-fields/groups', [
            'entity_type' => 'opportunity',
            'scope' => 'entity',
            'name' => 'Opportunity Details',
            'description' => 'Opportunity-specific fields',
        ])
        ->assertCreated()
        ->assertJsonPath('data.entity_type', 'opportunity')
        ->assertJsonPath('data.scope', 'entity')
        ->assertJsonPath('data.scope_key', 'entity')
        ->assertJsonPath('data.name', 'Opportunity Details');

    $this->assertDatabaseHas('custom_field_groups', [
        'entity_type' => 'opportunity',
        'scope_key' => 'entity',
        'name' => 'Opportunity Details',
    ]);
});

test('an admin can create a category scoped group for a category', function () {
    $category = ProductCategory::factory()->create();

    $this->actingAs(adminUser())
        ->postJson('/api/v1/custom-fields/groups', [
            'entity_type' => 'product',
            'scope' => 'category',
            'category_id' => $category->id,
            'name' => 'Uniform Specs',
        ])
        ->assertCreated()
        ->assertJsonPath('data.entity_type', 'product')
        ->assertJsonPath('data.scope', 'category')
        ->assertJsonPath('data.category_id', $category->id)
        ->assertJsonPath('data.name', 'Uniform Specs');
});

test('group creation rejects a duplicate target and an unsupported category scope', function () {
    $admin = adminUser();
    $category = ProductCategory::factory()->create();

    $payload = [
        'entity_type' => 'customer',
        'scope' => 'entity',
        'name' => 'Customer Fields',
    ];

    $this->actingAs($admin)->postJson('/api/v1/custom-fields/groups', $payload)->assertCreated();
    $this->actingAs($admin)->postJson('/api/v1/custom-fields/groups', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['entity_type']);

    $this->actingAs($admin)->postJson('/api/v1/custom-fields/groups', [
        'entity_type' => 'customer',
        'scope' => 'category',
        'category_id' => $category->id,
        'name' => 'Invalid Category Fields',
    ])->assertUnprocessable()->assertJsonValidationErrors(['scope']);
});

test('an admin can update a group name and description', function () {
    $group = CustomFieldGroup::factory()->forEntity('opportunity')->create();

    $this->actingAs(adminUser())
        ->putJson("/api/v1/custom-fields/groups/{$group->id}", [
            'name' => 'Pipeline Fields',
            'description' => 'Additional opportunity details',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Pipeline Fields')
        ->assertJsonPath('data.description', 'Additional opportunity details');
});

test('a manager cannot create or update custom field groups', function () {
    $group = CustomFieldGroup::factory()->forEntity('customer')->create();
    $manager = managerUser();

    $this->actingAs($manager)
        ->postJson('/api/v1/custom-fields/groups', [
            'entity_type' => 'opportunity',
            'scope' => 'entity',
            'name' => 'Forbidden',
        ])
        ->assertForbidden();

    $this->actingAs($manager)
        ->putJson("/api/v1/custom-fields/groups/{$group->id}", ['name' => 'Forbidden'])
        ->assertForbidden();
});

test('deleting a group removes every definition value and the group', function () {
    $admin = adminUser();
    $category = ProductCategory::factory()->create();
    $group = CustomFieldGroup::factory()->forCategory($category->id)->create();
    $first = CustomFieldDefinition::factory()->create(['group_id' => $group->id, 'name' => 'aisle']);
    CustomFieldDefinition::factory()->create(['group_id' => $group->id, 'name' => 'rack']);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'category_custom_fields' => ['aisle' => 'A3', 'rack' => 'R2'],
    ]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/custom-fields/groups/{$group->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('custom_field_groups', ['id' => $group->id]);
    $this->assertDatabaseMissing('custom_field_definitions', ['id' => $first->id]);
    expect($product->fresh()->category_custom_fields)->toBe([]);
});

test('a manager cannot delete a custom field group', function () {
    $group = CustomFieldGroup::factory()->forEntity('customer')->create();

    $this->actingAs(managerUser())
        ->deleteJson("/api/v1/custom-fields/groups/{$group->id}")
        ->assertForbidden();
});

test('a sales rep cannot create update or delete custom field groups', function () {
    $group = CustomFieldGroup::factory()->forEntity('customer')->create();
    $salesRep = salesRepUser();

    $this->actingAs($salesRep)
        ->postJson('/api/v1/custom-fields/groups', [
            'entity_type' => 'opportunity',
            'scope' => 'entity',
            'name' => 'Forbidden',
        ])
        ->assertForbidden();

    $this->actingAs($salesRep)
        ->putJson("/api/v1/custom-fields/groups/{$group->id}", ['name' => 'Forbidden'])
        ->assertForbidden();

    $this->actingAs($salesRep)
        ->deleteJson("/api/v1/custom-fields/groups/{$group->id}")
        ->assertForbidden();
});
