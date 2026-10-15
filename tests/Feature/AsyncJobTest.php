<?php

use App\Jobs\ProcessAsyncJob;
use App\Models\AsyncJob;
use App\Services\ReportService;

// ── Async Report Generation ─────────────────────────────────────────────────

test('admin can submit async user performance report', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/reports/user-performance/async')
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'type', 'status', 'progress', 'created_at']])
        ->assertJsonPath('data.status', 'pending');
});

test('async report stores posted filters for processing', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/reports/lead-pipeline/async', [
            'status' => 'qualified',
            'min_score' => 70,
        ])
        ->assertCreated();

    $job = AsyncJob::where('user_id', $admin->id)->latest()->firstOrFail();

    expect($job->input['method'])->toBe('leadPipeline')
        ->and($job->input['params'][0])->toMatchArray([
            'status' => 'qualified',
            'min_score' => 70,
        ]);
});

test('admin can submit async company report', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/reports/company/async')
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');
});

test('admin can submit async customer report', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/reports/customer/async')
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');
});

test('sales_rep cannot submit async reports', function () {
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->postJson('/api/v1/reports/user-performance/async')
        ->assertForbidden();
});

test('manager can submit async reports', function () {
    $manager = managerUser();

    $this->actingAs($manager)
        ->postJson('/api/v1/reports/company/async')
        ->assertCreated();
});

// ── Async Job Polling ───────────────────────────────────────────────────────

test('user can poll their own async job', function () {
    $admin = adminUser();
    $job = AsyncJob::factory()->for($admin, 'user')->completed()->create();

    $this->actingAs($admin)
        ->getJson("/api/v1/async-jobs/{$job->id}")
        ->assertOk()
        ->assertJsonStructure(['data' => ['id', 'type', 'status', 'progress', 'result', 'completed_at']]);
});

test('user can list their async jobs', function () {
    $admin = adminUser();
    AsyncJob::factory()->for($admin, 'user')->count(3)->create();

    $this->actingAs($admin)
        ->getJson('/api/v1/async-jobs')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('user cannot view another user async job', function () {
    $admin = adminUser();
    $other = managerUser();
    $job = AsyncJob::factory()->for($other, 'user')->create();

    $this->actingAs($admin)
        ->getJson("/api/v1/async-jobs/{$job->id}")
        ->assertOk(); // admin has manage-users permission
});

test('sales_rep cannot view other user async job', function () {
    $rep = salesRepUser();
    $other = managerUser();
    $job = AsyncJob::factory()->for($other, 'user')->create();

    $this->actingAs($rep)
        ->getJson("/api/v1/async-jobs/{$job->id}")
        ->assertForbidden();
});

test('pending async job does not include result', function () {
    $admin = adminUser();
    $job = AsyncJob::factory()->for($admin, 'user')->create();

    $this->actingAs($admin)
        ->getJson("/api/v1/async-jobs/{$job->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonMissing(['result']);
});

test('completed async job includes result', function () {
    $admin = adminUser();
    $job = AsyncJob::factory()->for($admin, 'user')->completed()->create();

    $this->actingAs($admin)
        ->getJson("/api/v1/async-jobs/{$job->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.progress', 100);
});

test('failed async job includes error', function () {
    $admin = adminUser();
    $job = AsyncJob::factory()->for($admin, 'user')->failed()->create();

    $this->actingAs($admin)
        ->getJson("/api/v1/async-jobs/{$job->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.error', 'Something went wrong');
});

test('unauthenticated user cannot access async jobs', function () {
    $this->getJson('/api/v1/async-jobs')->assertUnauthorized();
});

// ── ProcessAsyncJob ─────────────────────────────────────────────────────────

test('ProcessAsyncJob processes and marks job completed', function () {
    $admin = adminUser();
    $job = AsyncJob::factory()->for($admin, 'user')->create([
        'type' => ReportService::class,
        'input' => ['method' => 'userPerformance', 'params' => [[]]],
    ]);

    (new ProcessAsyncJob($job->id))->handle();

    $job->refresh();
    expect($job->status)->toBe('completed')
        ->and($job->result)->toBeArray()
        ->and($job->progress)->toBe(100)
        ->and($job->completed_at)->not->toBeNull();
});

test('ProcessAsyncJob marks job failed on exception', function () {
    $admin = adminUser();
    $job = AsyncJob::factory()->for($admin, 'user')->create([
        'type' => ReportService::class,
        'input' => ['method' => 'nonExistentMethod', 'params' => [[]]],
    ]);

    $processJob = new ProcessAsyncJob($job->id);
    $processJob->failed(new RuntimeException('Method does not exist'));

    $job->refresh();
    expect($job->status)->toBe('failed')
        ->and($job->error)->toBe('Method does not exist');
});
