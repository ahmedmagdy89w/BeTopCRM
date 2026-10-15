<?php

namespace Database\Factories;

use App\Models\CustomFieldGroup;
use App\Models\User;
use App\Services\CustomFieldEntityRegistry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomFieldGroup>
 */
class CustomFieldGroupFactory extends Factory
{
    protected $model = CustomFieldGroup::class;

    public function definition(): array
    {
        return [
            'entity_type' => 'product',
            'scope' => CustomFieldEntityRegistry::SCOPE_ENTITY,
            'category_id' => null,
            'scope_key' => CustomFieldEntityRegistry::scopeKey(),
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function forEntity(string $entityType, string $name = 'Default Fields'): static
    {
        return $this->state([
            'entity_type' => $entityType,
            'scope' => CustomFieldEntityRegistry::SCOPE_ENTITY,
            'category_id' => null,
            'scope_key' => CustomFieldEntityRegistry::scopeKey(),
            'name' => $name,
        ]);
    }

    public function forCategory(int $categoryId, string $name = 'Category Fields'): static
    {
        return $this->state([
            'entity_type' => 'product',
            'scope' => CustomFieldEntityRegistry::SCOPE_CATEGORY,
            'category_id' => $categoryId,
            'scope_key' => CustomFieldEntityRegistry::scopeKey(
                CustomFieldEntityRegistry::SCOPE_CATEGORY,
                $categoryId,
            ),
            'name' => $name,
        ]);
    }
}
