<?php

namespace App\Jobs;

use App\Models\AsyncJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessAsyncJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(private int $asyncJobId) {}

    public function handle(): void
    {
        $asyncJob = AsyncJob::findOrFail($this->asyncJobId);

        if (! $asyncJob->isPending()) {
            return;
        }

        $asyncJob->markProcessing();

        $service = app($asyncJob->type);
        $method = $asyncJob->input['method'];
        $params = $asyncJob->input['params'] ?? [];

        $result = $service->{$method}(...$params);

        $asyncJob->markCompleted($result);
    }

    public function failed(\Throwable $e): void
    {
        $asyncJob = AsyncJob::find($this->asyncJobId);
        $asyncJob?->markFailed($e->getMessage());
    }
}
