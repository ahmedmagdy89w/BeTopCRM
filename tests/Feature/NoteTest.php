<?php

use App\Models\Customer;
use App\Models\Note;

// ── CRUD (happy path) ─────────────────────────────────────────────────────────

test('admin can list notes on a lead', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);
    Note::factory(3)->create(['notable_type' => 'App\\Models\\Customer', 'notable_id' => $lead->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$lead->id}/notes")
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('admin can create a note on a lead', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$lead->id}/notes", ['body' => 'This is a test note.'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'This is a test note.');

    $this->assertDatabaseHas('notes', ['body' => 'This is a test note.', 'notable_id' => $lead->id]);
});

test('admin can create a reply note', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);
    $parent = Note::factory()->create([
        'notable_type' => 'App\\Models\\Customer',
        'notable_id' => $lead->id,
        'user_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$lead->id}/notes", [
            'body' => 'Reply note.',
            'parent_id' => $parent->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.parent_id', $parent->id);
});

test('admin can update own note', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);
    $note = Note::factory()->create([
        'notable_type' => 'App\\Models\\Customer',
        'notable_id' => $lead->id,
        'user_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->putJson("/api/v1/notes/{$note->id}", ['body' => 'Edited body.'])
        ->assertOk()
        ->assertJsonPath('data.body', 'Edited body.');
});

test('admin can delete a note', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);
    $note = Note::factory()->create([
        'notable_type' => 'App\\Models\\Customer',
        'notable_id' => $lead->id,
        'user_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/notes/{$note->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('notes', ['id' => $note->id, 'deleted_at' => null]);
});

// ── Pin / Unpin ───────────────────────────────────────────────────────────────

test('admin can toggle pin on a note', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);
    $note = Note::factory()->create([
        'notable_type' => 'App\\Models\\Customer',
        'notable_id' => $lead->id,
        'user_id' => $admin->id,
        'is_pinned' => false,
    ]);

    $this->actingAs($admin)
        ->postJson("/api/v1/notes/{$note->id}/toggle-pin")
        ->assertOk()
        ->assertJsonPath('data.is_pinned', true);

    // Toggle again — should unpin
    $this->actingAs($admin)
        ->postJson("/api/v1/notes/{$note->id}/toggle-pin")
        ->assertOk()
        ->assertJsonPath('data.is_pinned', false);
});

// ── Authorization ─────────────────────────────────────────────────────────────

test('sales_rep cannot edit another user\'s note', function () {
    $admin = adminUser();
    $rep = salesRepUser();
    $lead = Customer::factory()->create(['created_by' => $rep->id, 'assigned_to' => $rep->id]);
    $note = Note::factory()->create([
        'notable_type' => 'App\\Models\\Customer',
        'notable_id' => $lead->id,
        'user_id' => $admin->id,
    ]);

    $this->actingAs($rep)
        ->putJson("/api/v1/notes/{$note->id}", ['body' => 'Hijacked.'])
        ->assertForbidden();
});

test('unauthenticated request on notes returns 401', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);

    $this->getJson("/api/v1/customers/{$lead->id}/notes")->assertUnauthorized();
});

// ── Validation ────────────────────────────────────────────────────────────────

test('create note requires body', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$lead->id}/notes", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);
});
