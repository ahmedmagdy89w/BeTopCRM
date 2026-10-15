<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        return [
            'name'        => $this->faker->unique()->words(2, true),
            'parent_id'   => null,
            'description' => $this->faker->sentence(),
            'sort_order'  => $this->faker->numberBetween(0, 100),
            'created_by'  => User::factory(),
        ];
    }
}
