<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name'        => $this->faker->unique()->words(3, true),
            'sku'         => strtoupper($this->faker->unique()->bothify('PRD-###??')),
            'description' => $this->faker->sentence(),
            'unit_price'  => $this->faker->randomFloat(2, 10, 1000),
            'currency'    => 'USD',
            'is_active'   => true,
            'created_by'  => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
