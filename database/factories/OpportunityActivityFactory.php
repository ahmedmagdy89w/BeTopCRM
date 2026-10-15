<?php

namespace Database\Factories;

use App\Models\Opportunity;
use App\Models\OpportunityActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpportunityActivityFactory extends Factory
{
    protected $model = OpportunityActivity::class;

    public function definition(): array
    {
        return [
            'opportunity_id' => Opportunity::factory(),
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(OpportunityActivity::TYPES),
            'subject' => $this->faker->sentence(4),
            'description' => $this->faker->sentence(),
            'outcome' => null,
            'due_date' => $this->faker->dateTimeBetween('-1 month', '+1 month'),
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'completed_at' => now(),
            'outcome' => $this->faker->randomElement(OpportunityActivity::OUTCOMES),
        ]);
    }
}
