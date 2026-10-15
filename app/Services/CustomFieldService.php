<?php

namespace App\Services;

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldGroup;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomFieldService
{
    public function __construct(private CustomFieldEntityRegistry $registry) {}

    /**
     * The single group for a model. Created on demand so the very first definition
     * for an entity does not need a separate "create group" step.
     *
     * The actor is threaded in rather than read from auth(), because this service is
     * also reachable from console and queue contexts where there is no session.
     */
    public function entityGroup(string $entityType, ?int $createdBy = null): CustomFieldGroup
    {
        $this->registry->guard($entityType);

        $scopeKey = CustomFieldEntityRegistry::scopeKey();

        return CustomFieldGroup::query()
            ->where('entity_type', $entityType)
            ->where('scope_key', $scopeKey)
            ->first() ?? $this->createGroup($entityType, $scopeKey, null, $createdBy);
    }

    public function categoryGroup(string $entityType, int $categoryId, ?int $createdBy = null): CustomFieldGroup
    {
        $this->registry->guard($entityType, CustomFieldEntityRegistry::SCOPE_CATEGORY);

        $scopeKey = CustomFieldEntityRegistry::scopeKey(
            CustomFieldEntityRegistry::SCOPE_CATEGORY,
            $categoryId,
        );

        return CustomFieldGroup::query()
            ->where('entity_type', $entityType)
            ->where('scope_key', $scopeKey)
            ->first() ?? $this->createGroup($entityType, $scopeKey, $categoryId, $createdBy);
    }

    public function findEntityGroup(string $entityType): ?CustomFieldGroup
    {
        $this->registry->guard($entityType);

        return CustomFieldGroup::query()
            ->with('definitions.group')
            ->where('entity_type', $entityType)
            ->where('scope', CustomFieldEntityRegistry::SCOPE_ENTITY)
            ->first();
    }

    public function findCategoryGroup(string $entityType, int $categoryId): ?CustomFieldGroup
    {
        $this->registry->guard($entityType, CustomFieldEntityRegistry::SCOPE_CATEGORY);

        return CustomFieldGroup::query()
            ->with('definitions.group')
            ->where('entity_type', $entityType)
            ->where('scope', CustomFieldEntityRegistry::SCOPE_CATEGORY)
            ->where('category_id', $categoryId)
            ->first();
    }

    public function updateGroup(CustomFieldGroup $group, array $data): CustomFieldGroup
    {
        $group->update($data);

        return $group->fresh(['definitions', 'category']);
    }

    public function deleteGroup(CustomFieldGroup $group): void
    {
        DB::transaction(function () use ($group): void {
            $entityTable = $this->registry->table($group->entity_type);

            foreach ($group->definitions as $definition) {
                $this->stripKeyFromRows(
                    $entityTable,
                    $definition->valuesColumn($this->registry),
                    $definition->name,
                );
            }

            $group->delete();
        });
    }

    /**
     * @return Collection<int, CustomFieldDefinition>
     */
    public function listDefinitions(
        string $entityType,
        string $scope = CustomFieldEntityRegistry::SCOPE_ENTITY,
        ?int $categoryId = null,
    ): Collection {
        $this->registry->guard($entityType, $scope);

        if ($scope === CustomFieldEntityRegistry::SCOPE_CATEGORY) {
            $group = CustomFieldGroup::forCategory($categoryId)->first();

            return $group?->definitions ?? new Collection;
        }

        return CustomFieldDefinition::forEntity($entityType)->ordered()->get();
    }

    /**
     * A category with no group simply has no fields, which is what makes categories
     * fully independent rather than inheriting from a parent.
     *
     * @return Collection<int, CustomFieldDefinition>
     */
    public function listCategoryDefinitions(string $entityType, int $categoryId): Collection
    {
        $this->registry->guard($entityType, CustomFieldEntityRegistry::SCOPE_CATEGORY);

        return CustomFieldGroup::forCategory($categoryId)->first()?->definitions ?? new Collection;
    }

    /**
     * Every category-scoped definition for an entity, across all of its categories.
     *
     * Filtering is not scoped to one category, because a `ccf_aisle` filter on the
     * product list has to match products sitting in whichever category declares it.
     *
     * @return Collection<int, CustomFieldDefinition>
     */
    public function categoryScopedDefinitions(string $entityType): Collection
    {
        $this->registry->guard($entityType, CustomFieldEntityRegistry::SCOPE_CATEGORY);

        return CustomFieldDefinition::query()
            ->whereHas('group', fn ($group) => $group
                ->where('entity_type', $entityType)
                ->where('scope', CustomFieldEntityRegistry::SCOPE_CATEGORY))
            ->ordered()
            ->get();
    }

    public function createDefinition(string $entityType, array $data): CustomFieldDefinition
    {
        $group = $this->entityGroup($entityType, $data['created_by'] ?? null);

        return CustomFieldDefinition::create([
            ...$data,
            'group_id' => $group->id,
        ]);
    }

    public function createCategoryDefinition(string $entityType, int $categoryId, array $data): CustomFieldDefinition
    {
        $group = $this->categoryGroup($entityType, $categoryId, $data['created_by'] ?? null);

        return CustomFieldDefinition::create([
            ...$data,
            'group_id' => $group->id,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    public function createCategoryDefinitions(string $entityType, int $categoryId, array $fields, ?int $createdBy = null): Collection
    {
        $this->registry->guard($entityType, CustomFieldEntityRegistry::SCOPE_CATEGORY);

        $group = $this->categoryGroup($entityType, $categoryId, $createdBy);

        return collect($fields)->map(
            fn (array $field) => CustomFieldDefinition::create([
                'group_id' => $group->id,
                'name' => $field['name'],
                'label' => $field['label'],
                'type' => $field['type'],
                'options' => $field['options'] ?? null,
                'is_required' => (bool) ($field['is_required'] ?? false),
                'is_filterable' => (bool) ($field['is_filterable'] ?? false),
                'sort_order' => (int) ($field['sort_order'] ?? 0),
                'created_by' => $createdBy,
            ])
        );
    }

    public function updateDefinition(CustomFieldDefinition $definition, array $data): CustomFieldDefinition
    {
        $definition->update($data);

        return $definition->fresh();
    }

    public function deleteDefinition(CustomFieldDefinition $definition): void
    {
        $entityType = $definition->group?->entity_type;

        if ($entityType !== null) {
            $this->stripKeyFromRows($this->registry->table($entityType), $definition->valuesColumn($this->registry), $definition->name);
        }

        $definition->delete();
    }

    /**
     * Drop one key from a JSON column across every row of a table, so a deleted
     * definition never leaves an orphaned value behind.
     */
    public function stripKeyFromRows(string $table, string $column, string $name): void
    {
        DB::statement(
            "UPDATE {$table} SET {$column} = JSON_REMOVE({$column}, ?) WHERE JSON_EXTRACT({$column}, ?) IS NOT NULL",
            ["\$.{$name}", "\$.{$name}"]
        );
    }

    /**
     * The single source of truth for how one field type is validated. Every request
     * and the frontend type map derive from this rather than repeating a switch.
     *
     * @return array<int, mixed>
     */
    public function ruleFor(CustomFieldDefinition|string $type): array
    {
        $type = $type instanceof CustomFieldDefinition ? $type->type : $type;

        return match ($type) {
            'text' => ['string', 'max:255'],
            'textarea' => ['string', 'max:2000'],
            'number', 'decimal' => ['numeric'],
            'date' => ['date_format:Y-m-d'],
            'datetime' => ['date'],
            'select' => ['string'],
            'multi_select' => ['array'],
            'boolean' => ['boolean'],
            'color' => ['string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'url' => ['url', 'max:255'],
            'email' => ['email', 'max:255'],
            'phone' => ['string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            default => ['string'],
        };
    }

    /**
     * Rules for the model-scoped `custom_fields` payload.
     *
     * @return array<string, array<int, mixed>>
     */
    public function buildValueRules(string $entityType): array
    {
        $rules = [];

        foreach (CustomFieldDefinition::forEntity($entityType)->get() as $definition) {
            $key = "custom_fields.{$definition->name}";

            $rules[$key] = $definition->is_required
                ? ['required', ...$this->ruleFor($definition)]
                : ['nullable', ...$this->ruleFor($definition)];

            if ($definition->type === 'select') {
                $rules[$key][] = Rule::in($definition->options ?? []);
            }

            if ($definition->type === 'multi_select') {
                $rules["{$key}.*"] = ['string', Rule::in($definition->options ?? [])];
            }
        }

        return $rules;
    }

    /**
     * Rules for the category-scoped `category_custom_fields` payload.
     *
     * @return array<string, array<int, mixed>>
     */
    public function buildCategoryValueRules(string $entityType, int $categoryId): array
    {
        $rules = [];

        foreach ($this->listCategoryDefinitions($entityType, $categoryId) as $definition) {
            $column = $this->registry->categoryValuesColumn($entityType);
            $key = "{$column}.{$definition->name}";

            $rules[$key] = $definition->is_required
                ? ['required', ...$this->ruleFor($definition)]
                : ['nullable', ...$this->ruleFor($definition)];

            if ($definition->type === 'select') {
                $rules[$key][] = Rule::in($definition->options ?? []);
            }

            if ($definition->type === 'multi_select') {
                $rules["{$key}.*"] = ['string', Rule::in($definition->options ?? [])];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function buildFilterRules(string $entityType): array
    {
        return CustomFieldDefinition::forEntity($entityType)
            ->filterable()
            ->get()
            ->mapWithKeys(fn ($def) => ["cf_{$def->name}" => $def->type])
            ->toArray();
    }

    /**
     * @return array<string, string>
     */
    public function buildCategoryFilterRules(string $entityType, int $categoryId): array
    {
        return $this->listCategoryDefinitions($entityType, $categoryId)
            ->filterable()
            ->get()
            ->mapWithKeys(fn ($def) => ["ccf_{$def->name}" => $def->type])
            ->toArray();
    }

    /**
     * Drop any key the target category does not actually declare.
     *
     * This is what makes a category change safe: a product moving from Pants to
     * T-Shirts keeps `size` (both declare it) and loses `inseam` (only Pants does).
     * It deliberately does *not* enforce required-ness - that is validation's job -
     * so this can run on the way to persisting without turning a re-categorisation
     * into a validation error.
     */
    public function reconcileCategoryValues(array $values, ?int $categoryId, string $entityType = 'product'): array
    {
        if ($categoryId === null) {
            return [];
        }

        $allowed = $this->listCategoryDefinitions($entityType, $categoryId)
            ->pluck('name')
            ->all();

        return array_intersect_key($values, array_flip($allowed));
    }

    /**
     * Keep only keys that still have a definition, so a client cannot invent
     * fields, and enforce required-ness for the scope that applies.
     */
    public function validateValues(
        string $entityType,
        array $values,
        string $scope = CustomFieldEntityRegistry::SCOPE_ENTITY,
        ?int $categoryId = null,
    ): array {
        $isCategory = $scope === CustomFieldEntityRegistry::SCOPE_CATEGORY && $categoryId !== null;

        $definitions = $isCategory
            ? $this->listCategoryDefinitions($entityType, $categoryId)
            : CustomFieldDefinition::forEntity($entityType)->get();

        $column = $isCategory
            ? $this->registry->categoryValuesColumn($entityType)
            : 'custom_fields';

        return $this->cleanValues($definitions, $values, $column);
    }

    /**
     * @param  Collection<int, CustomFieldDefinition>  $definitions
     */
    private function cleanValues(
        Collection $definitions,
        array $values,
        string $column,
    ): array {
        $cleaned = [];

        foreach ($definitions as $definition) {
            $name = $definition->name;
            $label = $definition->label;
            $key = "{$column}.{$name}";
            $value = $values[$name] ?? null;

            if ($value === null || $value === '') {
                if ($definition->is_required) {
                    throw ValidationException::withMessages([
                        $key => "The custom field '{$label}' is required.",
                    ]);
                }

                continue;
            }

            $cleaned[$name] = $value;
        }

        return $cleaned;
    }

    /**
     * FirstOrCreate on a unique index still races, so a duplicate key is retried
     * as a read rather than surfacing as a 500.
     */
    private function createGroup(string $entityType, string $scopeKey, ?int $categoryId = null, ?int $createdBy = null): CustomFieldGroup
    {
        $attributes = [
            'entity_type' => $entityType,
            'scope' => $categoryId === null
                ? CustomFieldEntityRegistry::SCOPE_ENTITY
                : CustomFieldEntityRegistry::SCOPE_CATEGORY,
            'category_id' => $categoryId,
            'name' => $categoryId === null
                ? Str::headline($entityType).' Fields'
                : 'Category Fields',
        ];

        try {
            return CustomFieldGroup::create([
                ...$attributes,
                'scope_key' => $scopeKey,
                'created_by' => $createdBy ?? auth()->id(),
            ]);
        } catch (QueryException) {
            return CustomFieldGroup::query()
                ->where('entity_type', $entityType)
                ->where('scope_key', $scopeKey)
                ->firstOrFail();
        }
    }
}
