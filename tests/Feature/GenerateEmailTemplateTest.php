<?php

use App\AI\Agents\EmailTemplateGeneratorAgent;
use App\Jobs\ProcessAsyncJob;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Ai;

// -- Happy path ---------------------------------------------------------------

test('admin can dispatch AI generate job and receives pending async job', function () {
    Queue::fake();

    $this->actingAs(adminUser())
        ->postJson('/api/v1/email-templates/generate', [
            'topic' => 'New product launch',
            'audience' => 'enterprise clients',
            'tone' => 'professional',
            'language' => 'en',
        ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'type', 'status', 'progress', 'created_at']])
        ->assertJsonPath('data.status', 'pending');

    Queue::assertPushed(ProcessAsyncJob::class);
});

test('completed job result contains subject, body, and placeholders', function () {
    config(['queue.default' => 'sync']);

    Ai::fakeAgent(EmailTemplateGeneratorAgent::class, [
        "SUBJECT: Exciting New Product Launch\nBODY:\nDear {{contact_name}},\n\nWe are thrilled to announce our newest product.\n\nBest regards,\n{{account_manager_name}}",
    ]);

    $jobId = $this->actingAs(adminUser())
        ->postJson('/api/v1/email-templates/generate', [
            'topic' => 'New product launch',
            'tone' => 'professional',
            'language' => 'en',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs(adminUser())
        ->getJson("/api/v1/async-jobs/{$jobId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.result.subject', 'Exciting New Product Launch')
        ->assertJsonStructure(['data' => ['result' => ['subject', 'body', 'placeholders']]]);
});

test('completed job result includes placeholders extracted from body', function () {
    config(['queue.default' => 'sync']);

    Ai::fakeAgent(EmailTemplateGeneratorAgent::class, [
        "SUBJECT: Hello {{contact_name}}\nBODY:\nDear {{contact_name}}, your deal {{deal_value}} is ready.",
    ]);

    $jobId = $this->actingAs(adminUser())
        ->postJson('/api/v1/email-templates/generate', ['topic' => 'Deal closing'])
        ->assertCreated()
        ->json('data.id');

    $result = $this->actingAs(adminUser())
        ->getJson("/api/v1/async-jobs/{$jobId}")
        ->assertOk()
        ->json('data.result');

    expect($result['placeholders'])
        ->toContain('contact_name')
        ->toContain('deal_value');
});

test('manager can dispatch AI generate job', function () {
    Queue::fake();

    $this->actingAs(managerUser())
        ->postJson('/api/v1/email-templates/generate', ['topic' => 'Test topic'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');
});

test('generate job does not persist a template to database', function () {
    config(['queue.default' => 'sync']);

    Ai::fakeAgent(EmailTemplateGeneratorAgent::class, [
        "SUBJECT: Test\nBODY:\nTest body.",
    ]);

    $this->actingAs(adminUser())
        ->postJson('/api/v1/email-templates/generate', ['topic' => 'Test topic'])
        ->assertCreated();

    $this->assertDatabaseCount('email_templates', 0);
});

// -- Localization -------------------------------------------------------------

test('completed job result contains arabic content when language is ar', function () {
    config(['queue.default' => 'sync']);

    Ai::fakeAgent(EmailTemplateGeneratorAgent::class, [
        "SUBJECT: \u0645\u0631\u062d\u0628\u0627\u064b {{contact_name}}\nBODY:\n\u0639\u0632\u064a\u0632\u064a {{contact_name}}\u060c \u0646\u0633\u0639\u062f \u0628\u062a\u0642\u062f\u064a\u0645 \u0639\u0631\u0636\u0646\u0627.",
    ]);

    $jobId = $this->actingAs(adminUser())
        ->postJson('/api/v1/email-templates/generate', [
            'topic' => 'New product',
            'language' => 'ar',
        ])
        ->assertCreated()
        ->json('data.id');

    $subject = $this->actingAs(adminUser())
        ->getJson("/api/v1/async-jobs/{$jobId}")
        ->assertOk()
        ->json('data.result.subject');

    expect($subject)->toContain('{{contact_name}}');
});

// -- Authorization ------------------------------------------------------------

test('unauthenticated request on generate returns 401', function () {
    $this->postJson('/api/v1/email-templates/generate', ['topic' => 'Test'])
        ->assertUnauthorized();
});

test('sales_rep cannot generate email template', function () {
    $this->actingAs(salesRepUser())
        ->postJson('/api/v1/email-templates/generate', [
            'topic' => 'New product launch',
        ])
        ->assertForbidden();
});

// -- Validation ---------------------------------------------------------------

test('generate requires topic', function () {
    $this->actingAs(adminUser())
        ->postJson('/api/v1/email-templates/generate', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['topic']);
});

test('generate validates tone is one of allowed values', function () {
    $this->actingAs(adminUser())
        ->postJson('/api/v1/email-templates/generate', [
            'topic' => 'Test',
            'tone' => 'aggressive',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tone']);
});

test('generate validates language is en or ar', function () {
    $this->actingAs(adminUser())
        ->postJson('/api/v1/email-templates/generate', [
            'topic' => 'Test',
            'language' => 'fr',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['language']);
});
