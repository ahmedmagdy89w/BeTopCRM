<?php

namespace App\Filters;

use App\Services\CustomFieldEntityRegistry;
use App\Services\CustomFieldService;
use Illuminate\Database\Eloquent\Builder;

class ProductFilter extends BaseFilter
{
    private const SORTABLE = ['name', 'sku', 'unit_price', 'is_active', 'created_at', 'category_id'];

    public function __construct(array $filters, private bool $includeInactiveByDefault = false)
    {
        parent::__construct($filters);
    }

    public function apply(Builder $query): Builder
    {
        if (! empty($this->filters['search'])) {
            $query->search($this->filters['search']);
        }

        if (isset($this->filters['is_active'])) {
            $query->where('is_active', filter_var($this->filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        } elseif (! $this->includeInactiveByDefault) {
            $query->active();
        }

        if (! empty($this->filters['category_id'])) {
            $query->inCategory((int) $this->filters['category_id']);
        }

        $query = (new CustomFieldFilter(
            $this->filters,
            app(CustomFieldService::class),
            app(CustomFieldEntityRegistry::class),
        ))->apply($query);

        return $this->applySort($query, self::SORTABLE, 'name', 'asc');
    }
}
