<?php

namespace App\Services;

use App\Jobs\ProcessAsyncJob;
use App\Models\AsyncJob;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class AsyncJobService
{
    /**
     * Dispatch a new async job.
     *
     * @param  string  $serviceClass  Fully-qualified class name of the service to call
     * @param  string  $method  Method name on that service
     * @param  array  $params  Positional arguments to pass to the method
     * @param  User  $user  Authenticated user who owns this job
     */
    public function dispatch(string $serviceClass, string $method, array $params, User $user): AsyncJob
    {
        $asyncJob = AsyncJob::create([
            'user_id' => $user->id,
            'type' => $serviceClass,
            'status' => AsyncJob::STATUS_PENDING,
            'input' => [
                'method' => $method,
                'params' => $params,
            ],
        ]);

        ProcessAsyncJob::dispatch($asyncJob->id);

        return $asyncJob;
    }

    public function find(int $id): AsyncJob
    {
        return AsyncJob::findOrFail($id);
    }

    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = AsyncJob::where('user_id', $user->id)->latest();

        if (! empty($filters['type'])) {
            $query->byType($filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
