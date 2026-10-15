<?php

namespace Database\Factories;

use App\Models\Tax;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tax>
 */
class TaxFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'VAT '.$this->faker->unique()->numberBetween(1, 999),
            'percent' => $this->faker->randomElement(['0.00', '5.00', '14.00', '15.00']),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
