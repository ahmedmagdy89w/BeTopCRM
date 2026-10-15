<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class QuotationFilter extends BaseFilter
{
    public function apply(Builder $query): Builder
    {
        if (! empty($this->filters['search'])) {
            $term = $this->filters['search'];
            $query->where(function (Builder $query) use ($term): void {
                $query->where('quotation_number', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery->search($term))
                    ->orWhereHas('opportunity', fn (Builder $opportunityQuery) => $opportunityQuery->where('title', 'like', "%{$term}%"));
            });
        }

        if (! empty($this->filters['status'])) {
            $statuses = is_array($this->filters['status'])
                ? $this->filters['status']
                : explode(',', (string) $this->filters['status']);
            $query->whereIn('status', $statuses);
        }

        if (! empty($this->filters['customer_id'])) {
            $query->where('customer_id', (int) $this->filters['customer_id']);
        }

        if (! empty($this->filters['date_from'])) {
            $query->whereDate('valid_until', '>=', $this->filters['date_from']);
        }

        if (! empty($this->filters['date_to'])) {
            $query->whereDate('valid_until', '<=', $this->filters['date_to']);
        }

        return $query;
    }
}
