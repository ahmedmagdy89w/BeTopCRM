<?php

use App\Models\Customer;
use App\Models\Opportunity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// ── Upload & List ────────────────────────────────────────────────────────────

test('admin can upload attachment to a lead', function () {
    Storage::fake('local');

    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);
    $file = UploadedFile::fake()->create('report.pdf', 500, 'application/pdf');

    $this->actingAs($admin)
        ->post("/api/v1/customers/{$lead->id}/attachments", [
            'files' => [$file],
        ], ['Accept' => 'application/json'])
        ->assertStatus(200)
        ->assertJsonPath('data.0.original_name', 'report.pdf');

    $this->assertDatabaseHas('attachments', [
        'attachable_type' => Customer::class,
        'attachable_id' => $lead->id,
        'original_name' => 'report.pdf',
    ]);
});

test('admin can list attachments for a lead', function () {
    Storage::fake('local');

    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);
    $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

    $this->actingAs($admin)
        ->post("/api/v1/customers/{$lead->id}/attachments", ['files' => [$file]], ['Accept' => 'application/json']);

    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$lead->id}/attachments")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('admin can delete attachment', function () {
    Storage::fake('local');

    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);
    $file = UploadedFile::fake()->create('delete-me.pdf', 100, 'application/pdf');

    $response = $this->actingAs($admin)
        ->post("/api/v1/customers/{$lead->id}/attachments", ['files' => [$file]], ['Accept' => 'application/json']);

    $attachmentId = $response->json('data.0.id');

    $this->actingAs($admin)
        ->deleteJson("/api/v1/attachments/{$attachmentId}")
        ->assertNoContent();

    $this->assertDatabaseMissing('attachments', ['id' => $attachmentId]);
});

// ── Authorization ────────────────────────────────────────────────────────────

test('unauthenticated request on attachments returns 401', function () {
    $lead = Customer::factory()->create();
    $this->getJson("/api/v1/customers/{$lead->id}/attachments")->assertUnauthorized();
});

// ── Validation ───────────────────────────────────────────────────────────────

test('upload attachment requires a file', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$lead->id}/attachments", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['files']);
});

test('upload attachment rejects oversized files', function () {
    Storage::fake('local');

    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);
    // 11 MB file (exceeds 10 MB limit)
    $file = UploadedFile::fake()->create('huge.pdf', 11264, 'application/pdf');

    $this->actingAs($admin)
        ->post("/api/v1/customers/{$lead->id}/attachments", ['files' => [$file]], ['Accept' => 'application/json'])
        ->assertUnprocessable();
});

// ── Opportunities ──────────────────────────────────────────────────────────

test('admin can upload and list attachments on an opportunity', function () {
    Storage::fake('local');

    $admin = adminUser();
    $customer = Customer::factory()->create(['created_by' => $admin->id]);
    $opportunity = Opportunity::factory()->create(['customer_id' => $customer->id, 'created_by' => $admin->id]);
    $file = UploadedFile::fake()->create('plan.pdf', 200, 'application/pdf');

    $this->actingAs($admin)
        ->post("/api/v1/opportunities/{$opportunity->id}/attachments", [
            'files' => [$file],
        ], ['Accept' => 'application/json'])
        ->assertStatus(200)
        ->assertJsonPath('data.0.original_name', 'plan.pdf');

    $this->assertDatabaseHas('attachments', [
        'attachable_type' => Opportunity::class,
        'attachable_id' => $opportunity->id,
        'original_name' => 'plan.pdf',
    ]);

    $this->actingAs($admin)
        ->getJson("/api/v1/opportunities/{$opportunity->id}/attachments")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
