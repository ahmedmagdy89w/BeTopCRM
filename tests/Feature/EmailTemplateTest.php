<?php

use App\Models\EmailTemplate;

// ── CRUD (happy path) ────────────────────────────────────────────────────────

test('admin can list email templates', function () {
    $admin = adminUser();
    EmailTemplate::factory(3)->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson('/api/v1/email-templates')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('admin can create email template', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/email-templates', [
            'name'    => 'Welcome Email',
            'subject' => 'Welcome {{first_name}}!',
            'body'    => '<p>Hello {{first_name}}, welcome to our service.</p>',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Welcome Email');

    $this->assertDatabaseHas('email_templates', ['name' => 'Welcome Email']);
});

test('admin can show email template', function () {
    $admin    = adminUser();
    $template = EmailTemplate::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson("/api/v1/email-templates/{$template->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $template->id);
});

test('admin can update email template', function () {
    $admin    = adminUser();
    $template = EmailTemplate::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->putJson("/api/v1/email-templates/{$template->id}", ['name' => 'Revised Template'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Revised Template');
});

test('admin can delete email template', function () {
    $admin    = adminUser();
    $template = EmailTemplate::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/email-templates/{$template->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('email_templates', ['id' => $template->id]);
});

// ── Placeholder extraction ───────────────────────────────────────────────────

test('placeholders are auto-extracted from template body', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)
        ->postJson('/api/v1/email-templates', [
            'name'    => 'Placeholder Test',
            'subject' => 'Hello {{first_name}}',
            'body'    => '<p>Dear {{first_name}} {{last_name}}, your deal {{deal_title}} is ready.</p>',
        ])
        ->assertCreated();

    $placeholders = $response->json('data.placeholders');
    expect($placeholders)->toContain('first_name')
        ->toContain('last_name')
        ->toContain('deal_title');
});

// ── Preview ──────────────────────────────────────────────────────────────────

test('admin can preview email template', function () {
    $admin    = adminUser();
    $template = EmailTemplate::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/email-templates/{$template->id}/preview", [
            'sample_data' => ['first_name' => 'John'],
        ])
        ->assertOk();
});

// ── Authorization ────────────────────────────────────────────────────────────

test('unauthenticated request on email templates returns 401', function () {
    $this->getJson('/api/v1/email-templates')->assertUnauthorized();
});

test('sales_rep cannot create email template', function () {
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->postJson('/api/v1/email-templates', [
            'name'    => 'Test',
            'subject' => 'Sub',
            'body'    => 'Body',
        ])
        ->assertForbidden();
});

// ── Validation ───────────────────────────────────────────────────────────────

test('create email template requires name, subject, body', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/email-templates', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'subject', 'body']);
});
