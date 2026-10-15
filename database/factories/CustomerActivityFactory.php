<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerActivityFactory extends Factory
{
    protected $model = CustomerActivity::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(['onboarding', 'support', 'check_in', 'review', 'follow_up', 'renewal', 'training', 'general']),
            'channel' => $this->faker->randomElement(['call', 'email', 'meeting', null]),
            'subject' => $this->faker->sentence(4),
            'description' => $this->faker->sentence(),
            'outcome' => null,
            'due_date' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'completed_at' => now(),
            'outcome' => $this->faker->randomElement(['positive', 'neutral', 'negative', 'escalated']),
        ]);
    }
}
