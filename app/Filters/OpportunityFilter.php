<?php

namespace App\Filters;

use App\Services\CustomFieldEntityRegistry;
use App\Services\CustomFieldService;
use Illuminate\Database\Eloquent\Builder;

class OpportunityFilter extends BaseFilter
{
    private const SORTABLE = ['created_at', 'amount', 'expected_close_date', 'stage'];

    public function __construct(array $filters, private ?int $customerId = null)
    {
        parent::__construct($filters);
    }

    public function apply(Builder $query): Builder
    {
        if ($this->customerId !== null) {
            $query->where('customer_id', $this->customerId);
        }

        if (! empty($this->filters['stage'])) {
            $stages = is_array($this->filters['stage'])
                ? $this->filters['stage']
                : explode(',', (string) $this->filters['stage']);

            $query->whereIn('stage', $stages);
        }

        if (! empty($this->filters['status'])) {
            if ($this->filters['status'] === 'open') {
                $query->open();
            } elseif ($this->filters['status'] === 'closed') {
                $query->whereIn('stage', ['won', 'lost']);
            }
        } elseif (! empty($this->filters['open'])) {
            $query->open();
        }

        if (! empty($this->filters['assigned_to'])) {
            $query->where('assigned_to', (int) $this->filters['assigned_to']);
        }

        if (! empty($this->filters['search'])) {
            $term = $this->filters['search'];

            $query->where(function (Builder $nested) use ($term): void {
                $nested->where('title', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('company_name', 'like', "%{$term}%")
                        ->orWhere('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%"));
            });
        }

        if (! empty($this->filters['amount_min'])) {
            $query->where('amount', '>=', (float) $this->filters['amount_min']);
        }

        if (! empty($this->filters['amount_max'])) {
            $query->where('amount', '<=', (float) $this->filters['amount_max']);
        }

        if (! empty($this->filters['date_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['date_from']);
        }

        if (! empty($this->filters['date_to'])) {
            $query->whereDate('created_at', '<=', $this->filters['date_to']);
        }

        $query = (new CustomFieldFilter(
            $this->filters,
            app(CustomFieldService::class),
            app(CustomFieldEntityRegistry::class),
        ))->apply($query);

        return $this->applySort($query, self::SORTABLE);
    }
}
