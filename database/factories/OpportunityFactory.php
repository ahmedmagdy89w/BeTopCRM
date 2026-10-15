<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'title' => $this->faker->sentence(3),
            'stage' => $this->faker->randomElement(['prospecting', 'qualification', 'proposal', 'negotiation']),
            'amount' => $this->faker->randomFloat(2, 1000, 100000),
            'currency' => 'USD',
            'probability' => $this->faker->numberBetween(10, 90),
            'expected_close_date' => $this->faker->dateTimeBetween('+1 week', '+6 months')->format('Y-m-d'),
            'assigned_to' => User::factory(),
            'created_by' => User::factory(),
            'notes' => $this->faker->sentence(),
        ];
    }

    public function won(): static
    {
        return $this->state(['stage' => 'won', 'closed_at' => now(), 'probability' => 100]);
    }

    public function lost(): static
    {
        return $this->state(['stage' => 'lost', 'closed_at' => now(), 'probability' => 0]);
    }
}
