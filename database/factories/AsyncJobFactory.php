<?php

namespace Database\Factories;

use App\Models\AsyncJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AsyncJobFactory extends Factory
{
    protected $model = AsyncJob::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'App\\Services\\ReportService',
            'status' => AsyncJob::STATUS_PENDING,
            'input' => ['method' => 'userPerformance', 'params' => [[]]],
        ];
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => AsyncJob::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => AsyncJob::STATUS_COMPLETED,
            'progress' => 100,
            'result' => ['data' => [], 'summary' => []],
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => AsyncJob::STATUS_FAILED,
            'error' => 'Something went wrong',
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ]);
    }
}
