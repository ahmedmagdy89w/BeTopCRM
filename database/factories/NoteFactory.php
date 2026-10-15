<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NoteFactory extends Factory
{
    protected $model = Note::class;

    public function definition(): array
    {
        return [
            'notable_type' => Customer::class,
            'notable_id' => Customer::factory(),
            'user_id' => User::factory(),
            'body' => $this->faker->paragraph(),
            'is_pinned' => false,
            'parent_id' => null,
        ];
    }

    public function pinned(): static
    {
        return $this->state(['is_pinned' => true]);
    }
}
