<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'created_by'  => User::factory(),
            'title'       => $this->faker->sentence(4),
            'description' => $this->faker->sentence(),
            'priority'    => $this->faker->randomElement(Task::PRIORITIES),
            'status'      => 'pending',
            'due_date'    => now()->addDays(7),
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state([
            'due_date' => now()->subDays(3),
        ]);
    }
}
