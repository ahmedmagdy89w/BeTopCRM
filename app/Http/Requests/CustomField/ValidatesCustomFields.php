<?php

namespace App\Http\Requests\CustomField;

use App\Services\CustomFieldEntityRegistry;
use App\Services\CustomFieldService;

trait ValidatesCustomFields
{
    /**
     * Rules for the model-scoped `custom_fields` payload.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function customFieldRules(?string $entityType = null, bool $onlyWhenSubmitted = false): array
    {
        $entityType ??= $this->defaultCustomFieldEntityType();

        if (
            $entityType === null
            || ($onlyWhenSubmitted && ! $this->exists('custom_fields'))
            || ! app(CustomFieldEntityRegistry::class)->has($entityType)
        ) {
            return [];
        }

        return app(CustomFieldService::class)->buildValueRules($entityType);
    }

    /**
     * Rules for the category-scoped `category_custom_fields` payload. Returns []
     * when the request carries no category, because a product with no category
     * belongs to no category group and so has no category fields to satisfy.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function categoryCustomFieldRules(?string $entityType = null, bool $onlyWhenSubmitted = false): array
    {
        $registry = app(CustomFieldEntityRegistry::class);
        $entityType ??= $this->defaultCustomFieldEntityType();
        $categoryId = $this->input('category_id');

        if (
            $entityType === null
            || ($onlyWhenSubmitted && ! $this->exists('category_custom_fields'))
            || $categoryId === null
            || ! $registry->hasCategoryScope($entityType)
        ) {
            return [];
        }

        return app(CustomFieldService::class)
            ->buildCategoryValueRules($entityType, (int) $categoryId);
    }

    /**
     * Requests override this when their entity is not inferable from the payload.
     */
    protected function defaultCustomFieldEntityType(): ?string
    {
        return null;
    }
}
