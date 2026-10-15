<?php

namespace App\Filters;

use App\Models\Customer;
use App\Services\CustomFieldEntityRegistry;
use App\Services\CustomFieldService;
use Illuminate\Database\Eloquent\Builder;

class CustomerFilter extends BaseFilter
{
    public function apply(Builder $query): Builder
    {
        if (! empty($this->filters['search'])) {
            $query->search($this->filters['search']);
        }

        if (isset($this->filters['is_lead'])) {
            $query->where('is_lead', filter_var($this->filters['is_lead'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($this->filters['type'])) {
            $query->ofType($this->filters['type']);
        }

        if (! empty($this->filters['lead_status'])) {
            $query->leadStatus($this->filters['lead_status']);
        }

        if (! empty($this->filters['effective_status'])) {
            $status = $this->filters['effective_status'];

            if (in_array($status, Customer::LEAD_STATUSES, true)) {
                $query->where('is_lead', true)->leadStatus($status);
            } else {
                $query->where('is_lead', false)->status($status);
            }
        }

        if (! empty($this->filters['status'])) {
            $query->status($this->filters['status']);
        }

        if (! empty($this->filters['source'])) {
            $query->source($this->filters['source']);
        }

        if (! empty($this->filters['assigned_to'])) {
            $query->assignedTo((int) $this->filters['assigned_to']);
        }

        if (! empty($this->filters['account_manager_id'])) {
            $query->accountManager((int) $this->filters['account_manager_id']);
        }

        if (! empty($this->filters['health_below'])) {
            $query->healthBelow((int) $this->filters['health_below']);
        }

        if (! empty($this->filters['follow_up_due'])) {
            $query->followUpDue();
        }

        if (! empty($this->filters['contact'])) {
            $query->contactSearch($this->filters['contact']);
        }

        if (! empty($this->filters['city'])) {
            $query->addressSearch($this->filters['city']);
        }

        if (! empty($this->filters['address'])) {
            $query->addressSearch($this->filters['address']);
        }

        if (isset($this->filters['score_min'])) {
            $query->where('score', '>=', (int) $this->filters['score_min']);
        }

        if (isset($this->filters['score_max'])) {
            $query->where('score', '<=', (int) $this->filters['score_max']);
        }

        if (! empty($this->filters['tag'])) {
            $query->withTag($this->filters['tag']);
        }

        return (new CustomFieldFilter($this->filters, app(CustomFieldService::class), app(CustomFieldEntityRegistry::class)))
            ->apply($query);
    }
}
