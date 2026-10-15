<?php

namespace Database\Factories;

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomFieldDefinition>
 */
class CustomFieldDefinitionFactory extends Factory
{
    protected $model = CustomFieldDefinition::class;

    public function definition(): array
    {
        return [
            'group_id' => CustomFieldGroup::factory(),
            'name' => $this->faker->unique()->word(),
            'label' => $this->faker->words(2, true),
            'type' => 'text',
            'options' => null,
            'is_required' => false,
            'is_filterable' => false,
            'sort_order' => 0,
            'created_by' => User::factory(),
        ];
    }

    public function type(string $type, ?array $options = null): static
    {
        return $this->state([
            'type' => $type,
            'options' => $options,
        ]);
    }

    public function select(array $options = ['A', 'B', 'C']): static
    {
        return $this->type('select', $options);
    }

    public function multiSelect(array $options = ['A', 'B', 'C']): static
    {
        return $this->type('multi_select', $options);
    }

    public function required(bool $required = true): static
    {
        return $this->state(['is_required' => $required]);
    }

    public function filterable(bool $filterable = true): static
    {
        return $this->state(['is_filterable' => $filterable]);
    }
}
