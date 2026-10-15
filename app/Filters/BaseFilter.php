<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

abstract class BaseFilter
{
    public function __construct(protected array $filters) {}

    abstract public function apply(Builder $query): Builder;

    /**
     * Apply `sort_by` / `sort_dir` from the request against a fixed allowlist.
     *
     * The column is never interpolated from user input directly - an unknown key
     * silently falls back to the default rather than reaching the query builder.
     *
     * @param  list<string>  $sortable
     */
    protected function applySort(Builder $query, array $sortable, string $default = 'created_at', string $defaultDirection = 'desc'): Builder
    {
        $column = $this->filters['sort_by'] ?? null;

        if (! in_array($column, $sortable, true)) {
            $column = $default;
        }

        $direction = strtolower((string) ($this->filters['sort_dir'] ?? $defaultDirection)) === 'asc'
            ? 'asc'
            : 'desc';

        return $query->orderBy($column, $direction);
    }
}
