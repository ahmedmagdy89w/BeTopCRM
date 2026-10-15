<?php

use App\Models\Customer;
use App\Models\Task;

// ── CRUD (happy path) ────────────────────────────────────────────────────────

test('admin can list tasks', function () {
    $admin = adminUser();
    Task::factory(3)->create(['user_id' => $admin->id, 'created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson('/api/v1/tasks')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('admin can create task', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/tasks', [
            'title' => 'Follow up call',
            'priority' => 'high',
            'due_date' => now()->addDays(3)->toDateTimeString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Follow up call');
});

test('admin can show task', function () {
    $admin = adminUser();
    $task = Task::factory()->create(['user_id' => $admin->id, 'created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson("/api/v1/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $task->id);
});

test('admin can update task', function () {
    $admin = adminUser();
    $task = Task::factory()->create(['user_id' => $admin->id, 'created_by' => $admin->id]);

    $this->actingAs($admin)
        ->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Updated Task'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated Task');
});

test('admin can delete task', function () {
    $admin = adminUser();
    $task = Task::factory()->create(['user_id' => $admin->id, 'created_by' => $admin->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/tasks/{$task->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
});

// ── Complete ─────────────────────────────────────────────────────────────────

test('admin can complete a task', function () {
    $admin = adminUser();
    $task = Task::factory()->create(['user_id' => $admin->id, 'created_by' => $admin->id]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/tasks/{$task->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $this->assertNotNull($task->fresh()->completed_at);
});

// ── Polymorphic linking ───────────────────────────────────────────────────────

test('admin can create task linked to a lead', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson('/api/v1/tasks', [
            'title' => 'Lead Follow-up',
            'priority' => 'medium',
            'due_date' => now()->addDays(5)->toDateTimeString(),
            'entity_type' => 'customer',
            'entity_id' => $lead->id,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('tasks', [
        'taskable_type' => 'App\Models\Customer',
        'taskable_id' => $lead->id,
    ]);
});

// ── My Summary ───────────────────────────────────────────────────────────────

test('admin can get task summary', function () {
    $admin = adminUser();
    Task::factory(2)->create(['user_id' => $admin->id, 'created_by' => $admin->id, 'status' => 'pending']);
    Task::factory()->create(['user_id' => $admin->id, 'created_by' => $admin->id, 'status' => 'completed', 'completed_at' => now()]);

    $this->actingAs($admin)
        ->getJson('/api/v1/tasks/my-summary')
        ->assertOk()
        ->assertJsonStructure(['data' => ['overdue', 'due_today', 'upcoming_7_days', 'in_progress', 'total_pending']]);
});

// ── Authorization ────────────────────────────────────────────────────────────

test('unauthenticated request on tasks returns 401', function () {
    $this->getJson('/api/v1/tasks')->assertUnauthorized();
});

// ── Validation ───────────────────────────────────────────────────────────────

test('create task requires title', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/tasks', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});
