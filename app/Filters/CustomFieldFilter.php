<?php

namespace App\Filters;

use App\Models\CustomFieldDefinition;
use App\Services\CustomFieldEntityRegistry;
use App\Services\CustomFieldService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Applies `cf_<name>` and `ccf_<name>` filters to any registered model.
 *
 * The entity type and values column come from the registry, so a model needs no
 * knowledge of this class - it just has to be in config('crm.custom_fields.entities').
 */
class CustomFieldFilter
{
    public const MODEL_PREFIX = 'cf_';

    public const CATEGORY_PREFIX = 'ccf_';

    public function __construct(
        private array $filters,
        private CustomFieldService $customFields,
        private CustomFieldEntityRegistry $registry,
    ) {}

    public function apply(Builder $query): Builder
    {
        $entityType = $this->registry->typeForModel($query->getModel()::class);

        if ($entityType === null) {
            return $query;
        }

        $modelDefinitions = $this->keyByName(
            CustomFieldDefinition::forEntity($entityType)->get(),
        );

        $categoryDefinitions = $this->registry->hasCategoryScope($entityType)
            ? $this->keyByName($this->customFields->categoryScopedDefinitions($entityType))
            : [];

        foreach ($this->filters as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if (str_starts_with($key, self::CATEGORY_PREFIX)) {
                $this->applyOne(
                    $query,
                    $categoryDefinitions[substr($key, strlen(self::CATEGORY_PREFIX))] ?? null,
                    $this->registry->categoryValuesColumn($entityType),
                    $value,
                );

                continue;
            }

            if (str_starts_with($key, self::MODEL_PREFIX)) {
                $this->applyOne(
                    $query,
                    $modelDefinitions[substr($key, strlen(self::MODEL_PREFIX))] ?? null,
                    'custom_fields',
                    $value,
                );
            }
        }

        return $query;
    }

    /**
     * A missing or non-filterable definition is ignored rather than rejected, so a
     * stale bookmarked URL degrades to an unfiltered list instead of erroring.
     */
    private function applyOne(Builder $query, ?CustomFieldDefinition $definition, string $column, mixed $value): void
    {
        if ($definition === null || ! $definition->is_filterable) {
            return;
        }

        if ($definition->type === 'multi_select') {
            $this->applyContains($query, $column, $definition, $value);

            return;
        }

        [$operator, $needle] = $this->comparisonFor($definition, $value);

        if ($operator === null) {
            return;
        }

        $query->where("{$column}->{$definition->name}", $operator, $needle);
    }

    /**
     * The single place that decides how each type is compared. Text-ish types are
     * matched as substrings because their filter control is a search box, not a
     * picker; everything else is an equality test.
     *
     * @return array{0: string|null, 1: mixed} null operator means "cannot compare"
     */
    private function comparisonFor(CustomFieldDefinition $definition, mixed $value): array
    {
        return match ($definition->type) {
            'text', 'textarea', 'email', 'url', 'phone' => ['like', '%'.$value.'%'],
            'number', 'decimal' => is_numeric($value) ? ['=', $value] : [null, null],
            'boolean' => ['=', filter_var($value, FILTER_VALIDATE_BOOLEAN)],
            default => ['=', $value],
        };
    }

    /**
     * A multi-select matches if the stored array contains any of the selected
     * values, so passing several options widens the result set rather than
     * demanding a row contain all of them.
     */
    private function applyContains(Builder $query, string $column, CustomFieldDefinition $definition, mixed $value): void
    {
        $values = is_array($value)
            ? $value
            : (is_string($value) ? explode(',', $value) : [$value]);
        $values = array_values(array_filter(
            array_map(fn (mixed $candidate) => is_string($candidate) ? trim($candidate) : $candidate, $values),
            fn (mixed $candidate) => $candidate !== null && $candidate !== '',
        ));

        if ($values === []) {
            return;
        }

        $query->where(function (Builder $nested) use ($column, $definition, $values): void {
            foreach ($values as $candidate) {
                $nested->orWhereJsonContains("{$column}->{$definition->name}", $candidate);
            }
        });
    }

    /**
     * @param  Collection<int, CustomFieldDefinition>  $definitions
     * @return array<string, CustomFieldDefinition>
     */
    private function keyByName(Collection $definitions): array
    {
        return $definitions->keyBy('name')->all();
    }
}
