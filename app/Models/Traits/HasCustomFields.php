<?php

namespace App\Models\Traits;

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldGroup;
use App\Services\CustomFieldEntityRegistry;
use Illuminate\Support\Collection;

trait HasCustomFields
{
    public function initializeHasCustomFields(): void
    {
        $this->casts['custom_fields'] = 'array';

        // Read config directly rather than the container: trait initializers run from
        // Model::__construct and must not depend on the app being fully booted.
        $matched = null;

        foreach ((array) config('crm.custom_fields.entities', []) as $config) {
            if (($config['model'] ?? null) === static::class) {
                $matched = $config;

                break;
            }
        }

        if (! isset($matched['category']['values_column'])) {
            return;
        }

        $this->casts[$matched['category']['values_column']] = 'array';
    }

    /**
     * Resolved from the registry rather than declared per model, so a model can
     * never disagree with config and a new model needs no boilerplate.
     */
    public function customFieldEntityType(): ?string
    {
        return app(CustomFieldEntityRegistry::class)->typeForInstance($this);
    }

    /**
     * The category this record belongs to, when its entity type has category scope.
     */
    public function customFieldCategoryId(): ?int
    {
        $registry = app(CustomFieldEntityRegistry::class);
        $entityType = $this->customFieldEntityType();

        if ($entityType === null || ! $registry->hasCategoryScope($entityType)) {
            return null;
        }

        $categoryId = $this->getAttribute($registry->categoryForeignKey($entityType));

        return $categoryId !== null ? (int) $categoryId : null;
    }

    public function customFieldDefinitions(): Collection
    {
        return CustomFieldDefinition::forEntity($this->customFieldEntityType())
            ->ordered()
            ->get();
    }

    /**
     * Category-scoped definitions for this record, resolved from its category.
     */
    public function customFieldCategoryDefinitions(): Collection
    {
        $categoryId = $this->customFieldCategoryId();

        if ($categoryId === null) {
            return new Collection;
        }

        return CustomFieldGroup::forCategory($categoryId)
            ->first()?->definitions ?? new Collection;
    }

    public function customFieldValues(): array
    {
        return (array) ($this->custom_fields ?? []);
    }

    public function customFieldCategoryValues(): array
    {
        $registry = app(CustomFieldEntityRegistry::class);
        $entityType = $this->customFieldEntityType();

        if ($entityType === null || ! $registry->hasCategoryScope($entityType)) {
            return [];
        }

        return (array) ($this->getAttribute($registry->categoryValuesColumn($entityType)) ?? []);
    }
}
