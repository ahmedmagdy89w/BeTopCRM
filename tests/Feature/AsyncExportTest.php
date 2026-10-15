<?php

use App\Jobs\ProcessAsyncJob;
use App\Models\AsyncJob;
use App\Services\ReportExportService;
use Illuminate\Support\Facades\Storage;

// ── Async Export Submission ──────────────────────────────────────────────────

test('admin can submit async user performance export as xlsx', function () {
    $this->actingAs(adminUser())
        ->postJson('/api/v1/reports/user-performance/export/async', ['format' => 'xlsx'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonStructure(['data' => ['id', 'type', 'status', 'progress']]);
});

test('admin can submit async company export as pdf', function () {
    $this->actingAs(adminUser())
        ->postJson('/api/v1/reports/company/export/async', ['format' => 'pdf'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');
});

test('admin can submit async customer export', function () {
    $this->actingAs(adminUser())
        ->postJson('/api/v1/reports/customer/export/async')
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');
});

test('admin can submit async lead and product report exports', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/reports/lead-pipeline/export/async', ['format' => 'pdf', 'min_score' => 70])
        ->assertCreated();
    $this->actingAs($admin)
        ->postJson('/api/v1/reports/product-performance/export/async', ['format' => 'xlsx', 'date_from' => '2026-01-01'])
        ->assertCreated();

    $jobs = AsyncJob::where('user_id', $admin->id)->latest()->get()->keyBy(fn ($job) => $job->input['method']);

    expect($jobs['exportLeadPipeline']->input['params'][0]['min_score'])->toBe(70)
        ->and($jobs['exportLeadPipeline']->input['params'][1])->toBe('pdf')
        ->and($jobs['exportProductPerformance']->input['params'][0]['date_from'])->toBe('2026-01-01')
        ->and($jobs['exportProductPerformance']->input['params'][1])->toBe('xlsx');
});

test('async export defaults to xlsx when no format given', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/reports/user-performance/export/async')
        ->assertCreated();

    $job = AsyncJob::where('user_id', $admin->id)->latest()->first();

    expect($job->input['params'][1])->toBe('xlsx');
});

test('async export stores user locale in params', function () {
    $admin = adminUser();
    $admin->update(['locale' => 'ar']);

    $this->actingAs($admin)
        ->postJson('/api/v1/reports/user-performance/export/async')
        ->assertCreated();

    $job = AsyncJob::where('user_id', $admin->id)->latest()->first();

    expect($job->input['params'][2])->toBe('ar');
});

test('async export rejects invalid format', function () {
    $this->actingAs(adminUser())
        ->postJson('/api/v1/reports/user-performance/export/async', ['format' => 'csv'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('format');
});

test('sales_rep cannot submit async exports', function () {
    $this->actingAs(salesRepUser())
        ->postJson('/api/v1/reports/user-performance/export/async')
        ->assertForbidden();
});

test('manager can submit async exports', function () {
    $this->actingAs(managerUser())
        ->postJson('/api/v1/reports/company/export/async')
        ->assertCreated();
});

test('async export job stores ReportExportService as type', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/reports/customer/export/async')
        ->assertCreated();

    $job = AsyncJob::where('user_id', $admin->id)->latest()->first();

    expect($job->type)->toBe(ReportExportService::class);
    expect($job->input['method'])->toBe('exportCustomer');
});

// ── Download Endpoint ───────────────────────────────────────────────────────

test('user can download completed export file', function () {
    Storage::disk('local')->put('exports/test-report.xlsx', 'fake-excel-content');

    $admin = adminUser();
    $job = AsyncJob::factory()->for($admin, 'user')->completed()->create([
        'type' => ReportExportService::class,
        'result' => [
            'file_path' => 'exports/test-report.xlsx',
            'file_name' => 'user-performance-report.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
    ]);

    $this->actingAs($admin)
        ->get("/api/v1/async-jobs/{$job->id}/download")
        ->assertOk()
        ->assertDownload('user-performance-report.xlsx');

    Storage::disk('local')->delete('exports/test-report.xlsx');
});

test('download returns 404 for pending job', function () {
    $admin = adminUser();
    $job = AsyncJob::factory()->for($admin, 'user')->create();

    $this->actingAs($admin)
        ->get("/api/v1/async-jobs/{$job->id}/download")
        ->assertNotFound();
});

test('download returns 404 for failed job', function () {
    $admin = adminUser();
    $job = AsyncJob::factory()->for($admin, 'user')->failed()->create();

    $this->actingAs($admin)
        ->get("/api/v1/async-jobs/{$job->id}/download")
        ->assertNotFound();
});

test('download returns 404 when file is missing from disk', function () {
    $admin = adminUser();
    $job = AsyncJob::factory()->for($admin, 'user')->completed()->create([
        'result' => [
            'file_path' => 'exports/nonexistent-file.xlsx',
            'file_name' => 'report.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
    ]);

    $this->actingAs($admin)
        ->get("/api/v1/async-jobs/{$job->id}/download")
        ->assertNotFound();
});

test('sales_rep cannot download another users export', function () {
    $rep = salesRepUser();
    $other = managerUser();
    $job = AsyncJob::factory()->for($other, 'user')->completed()->create([
        'result' => [
            'file_path' => 'exports/test.xlsx',
            'file_name' => 'report.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
    ]);

    $this->actingAs($rep)
        ->get("/api/v1/async-jobs/{$job->id}/download")
        ->assertForbidden();
});

test('download returns data job result without file_path as 404', function () {
    $admin = adminUser();
    $job = AsyncJob::factory()->for($admin, 'user')->completed()->create([
        'result' => ['data' => [], 'summary' => []],
    ]);

    $this->actingAs($admin)
        ->get("/api/v1/async-jobs/{$job->id}/download")
        ->assertNotFound();
});

// ── ProcessAsyncJob with ReportExportService ────────────────────────────────

test('ProcessAsyncJob generates xlsx export file', function () {
    Storage::fake('local');

    $admin = adminUser();
    $job = AsyncJob::factory()->for($admin, 'user')->create([
        'type' => ReportExportService::class,
        'input' => ['method' => 'exportUserPerformance', 'params' => [[], 'xlsx', 'en']],
    ]);

    (new ProcessAsyncJob($job->id))->handle();

    $job->refresh();

    expect($job->status)->toBe('completed')
        ->and($job->result)->toHaveKeys(['file_path', 'file_name', 'mime_type'])
        ->and($job->result['file_name'])->toContain('user-performance-report')
        ->and($job->result['mime_type'])->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    Storage::disk('local')->assertExists($job->result['file_path']);
});

test('ProcessAsyncJob generates pdf export file', function () {
    Storage::fake('local');

    $admin = adminUser();
    $job = AsyncJob::factory()->for($admin, 'user')->create([
        'type' => ReportExportService::class,
        'input' => ['method' => 'exportCompany', 'params' => [[], 'pdf', 'en']],
    ]);

    (new ProcessAsyncJob($job->id))->handle();

    $job->refresh();

    expect($job->status)->toBe('completed')
        ->and($job->result['file_name'])->toContain('company-report')
        ->and($job->result['mime_type'])->toBe('application/pdf');

    Storage::disk('local')->assertExists($job->result['file_path']);
});
