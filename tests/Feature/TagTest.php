<?php

use App\Models\Customer;
use App\Models\Tag;

// ── CRUD (happy path) ────────────────────────────────────────────────────────

test('admin can list tags', function () {
    $admin = adminUser();
    Tag::factory(4)->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson('/api/v1/tags')
        ->assertOk()
        ->assertJsonCount(4, 'data');
});

test('admin can create tag', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/tags', [
            'name' => 'hot-lead',
            'color' => '#ff0000',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'hot-lead');

    $this->assertDatabaseHas('tags', ['name' => 'hot-lead']);
});

test('admin can update tag', function () {
    $admin = adminUser();
    $tag = Tag::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->putJson("/api/v1/tags/{$tag->id}", ['color' => '#00ff00'])
        ->assertOk()
        ->assertJsonPath('data.color', '#00ff00');
});

test('admin can delete tag', function () {
    $admin = adminUser();
    $tag = Tag::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/tags/{$tag->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
});

// ── Sync tags ────────────────────────────────────────────────────────────────

test('admin can sync tags on a lead', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);
    $tag1 = Tag::factory()->create(['created_by' => $admin->id]);
    $tag2 = Tag::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$lead->id}/tags", [
            'tag_ids' => [$tag1->id, $tag2->id],
        ])
        ->assertOk();

    expect($lead->fresh()->tags()->count())->toBe(2);
});

test('syncing tags replaces existing tags on lead', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);
    $tag1 = Tag::factory()->create(['created_by' => $admin->id]);
    $tag2 = Tag::factory()->create(['created_by' => $admin->id]);

    $lead->tags()->attach($tag1->id);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$lead->id}/tags", ['tag_ids' => [$tag2->id]])
        ->assertOk();

    expect($lead->fresh()->tags()->count())->toBe(1);
    expect($lead->fresh()->tags()->first()->id)->toBe($tag2->id);
});

// ── Filter by tag ────────────────────────────────────────────────────────────

test('admin can filter leads by tag', function () {
    $admin = adminUser();
    $tag = Tag::factory()->create(['created_by' => $admin->id]);
    $lead1 = Customer::factory()->create(['created_by' => $admin->id]);
    $lead2 = Customer::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$lead1->id}/tags", ['tag_ids' => [$tag->id]]);
    $this->actingAs($admin)
        ->getJson("/api/v1/customers?tag={$tag->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// ── Authorization ────────────────────────────────────────────────────────────

test('unauthenticated request on tags returns 401', function () {
    $this->getJson('/api/v1/tags')->assertUnauthorized();
});

test('sales_rep cannot create tag', function () {
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->postJson('/api/v1/tags', ['name' => 'test', 'color' => '#fff'])
        ->assertForbidden();
});

// ── Validation ───────────────────────────────────────────────────────────────

test('create tag requires name', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/tags', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});
