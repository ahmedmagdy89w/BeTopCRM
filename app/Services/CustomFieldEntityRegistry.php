<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class CustomFieldEntityRegistry
{
    public const SCOPE_ENTITY = 'entity';

    public const SCOPE_CATEGORY = 'category';

    /**
     * @return array<string, array{model: class-string, table: string, label: string, category?: array}>
     */
    public function all(): array
    {
        return config('crm.custom_fields.entities', []);
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->all());
    }

    public function has(string $entityType): bool
    {
        return array_key_exists($entityType, $this->all());
    }

    /**
     * @return array{model: class-string, table: string, label: string, category?: array}
     */
    public function get(string $entityType): array
    {
        if (! $this->has($entityType)) {
            throw new \InvalidArgumentException("Unknown custom field entity type [{$entityType}].");
        }

        return $this->all()[$entityType];
    }

    /**
     * @return class-string
     */
    public function modelClass(string $entityType): string
    {
        return $this->get($entityType)['model'];
    }

    public function table(string $entityType): string
    {
        return $this->get($entityType)['table'];
    }

    public function labelKey(string $entityType): string
    {
        return $this->get($entityType)['label'];
    }

    public function hasCategoryScope(string $entityType): bool
    {
        return isset($this->get($entityType)['category']);
    }

    public function categoryTable(string $entityType): string
    {
        return $this->categoryConfig($entityType)['table'];
    }

    public function categoryForeignKey(string $entityType): string
    {
        return $this->categoryConfig($entityType)['foreign_key'];
    }

    public function categoryLabelKey(string $entityType): string
    {
        return $this->categoryConfig($entityType)['label'];
    }

    /**
     * The column on the entity table holding category-scoped values. Deliberately
     * separate from 'custom_fields' so same-named fields in the two scopes
     * cannot collide.
     */
    public function categoryValuesColumn(string $entityType): string
    {
        return $this->categoryConfig($entityType)['values_column'];
    }

    /**
     * Reverse lookup, so HasCustomFields need not declare its own entity type.
     */
    public function typeForModel(string $modelClass): ?string
    {
        foreach ($this->all() as $entityType => $config) {
            if ($config['model'] === $modelClass) {
                return $entityType;
            }
        }

        return null;
    }

    public function typeForInstance(object $model): ?string
    {
        return $this->typeForModel($model::class);
    }

    /**
     * Normalise a scope into the descriptor used everywhere else, so callers never
     * branch on whether a category scope is even possible for this entity.
     *
     * @return array{entity_type: string, scope: string, category_id: int|null, table: string, values_column: string}
     */
    public function resolveScope(string $entityType, string $scope = self::SCOPE_ENTITY, ?int $categoryId = null): array
    {
        $config = $this->get($entityType);

        if ($scope === self::SCOPE_ENTITY) {
            return [
                'entity_type' => $entityType,
                'scope' => self::SCOPE_ENTITY,
                'category_id' => null,
                'table' => $config['table'],
                'values_column' => 'custom_fields',
            ];
        }

        $categoryConfig = $config['category'] ?? null;

        if ($categoryConfig === null) {
            throw new \InvalidArgumentException("Entity type [{$entityType}] does not support category scoped custom fields.");
        }

        return [
            'entity_type' => $entityType,
            'scope' => self::SCOPE_CATEGORY,
            'category_id' => $categoryId,
            'table' => $config['table'],
            'values_column' => $categoryConfig['values_column'],
        ];
    }

    /**
     * Validate a scope coming from user input (a URL segment or a request field).
     */
    public function guard(string $entityType, string $scope = self::SCOPE_ENTITY): void
    {
        if (! $this->has($entityType)) {
            throw ValidationException::withMessages([
                'entity_type' => "Unknown custom field entity type [{$entityType}].",
            ]);
        }

        if (! in_array($scope, [self::SCOPE_ENTITY, self::SCOPE_CATEGORY], true)) {
            throw ValidationException::withMessages([
                'scope' => "Unknown custom field scope [{$scope}].",
            ]);
        }

        if ($scope === self::SCOPE_CATEGORY && ! $this->hasCategoryScope($entityType)) {
            throw ValidationException::withMessages([
                'scope' => "Entity type [{$entityType}] does not support category scoped custom fields.",
            ]);
        }
    }

    /**
     * The derived, non-nullable key that backs the one-group-per-target unique index.
     */
    public static function scopeKey(string $scope = self::SCOPE_ENTITY, ?int $categoryId = null): string
    {
        return $scope === self::SCOPE_ENTITY
            ? self::SCOPE_ENTITY
            : self::SCOPE_CATEGORY.':'.$categoryId;
    }

    /**
     * @return array{table: string, foreign_key: string, label: string, values_column: string}
     */
    private function categoryConfig(string $entityType): array
    {
        $category = $this->get($entityType)['category'] ?? null;

        if ($category === null) {
            throw new \InvalidArgumentException("Entity type [{$entityType}] does not support category scoped custom fields.");
        }

        return $category;
    }
}
