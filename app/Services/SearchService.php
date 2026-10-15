<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\User;

class SearchService
{
    public function search(string $query, array $types, int $limit, User $user): array
    {
        $results = [];
        $totalCount = 0;

        if (in_array('customers', $types) && $user->can('view-customers')) {
            $items = $this->searchCustomers($query, $limit, $user, null);
            $results['customers'] = $items;
            $totalCount += count($items);
        }

        if (in_array('leads', $types) && $user->can('view-customers')) {
            $items = $this->searchCustomers($query, $limit, $user, true);
            $results['leads'] = $items;
            $totalCount += count($items);
        }

        if (in_array('opportunities', $types) && $user->can('view-opportunities')) {
            $items = $this->searchOpportunities($query, $limit);
            $results['opportunities'] = $items;
            $totalCount += count($items);
        }

        foreach (['leads', 'customers', 'opportunities'] as $type) {
            if (! isset($results[$type])) {
                $results[$type] = [];
            }
        }

        return ['query' => $query, 'results' => $results, 'total_count' => $totalCount];
    }

    private function baseCustomerQuery(User $user)
    {
        $query = Customer::query()->whereNull('deleted_at');

        if ($user->hasRole('sales_rep')) {
            $query->where(fn ($sq) => $sq->where('assigned_to', $user->id)->orWhere('account_manager_id', $user->id));
        }

        return $query;
    }

    private function searchCustomers(string $q, int $limit, User $user, ?bool $isLead): array
    {
        $query = $this->baseCustomerQuery($user);

        if ($isLead !== null) {
            $query->where('is_lead', $isLead);
        }

        $query->where(function ($sq) use ($q) {
            $sq->where('company_name', 'LIKE', "%{$q}%")
                ->orWhere('first_name', 'LIKE', "%{$q}%")
                ->orWhere('last_name', 'LIKE', "%{$q}%")
                ->orWhere('email', 'LIKE', "%{$q}%")
                ->orWhere('phone', 'LIKE', "%{$q}%")
                ->orWhere('contacts', 'LIKE', "%{$q}%")
                ->orWhere('addresses', 'LIKE', "%{$q}%");
        });

        return $query->limit($limit)->get()->map(function ($customer) use ($q) {
            return [
                'id' => $customer->id,
                'type' => $customer->is_lead ? 'lead' : 'customer',
                'title' => $customer->display_name,
                'subtitle' => implode(' — ', array_filter([$customer->email, $customer->effective_status])),
                'status' => $customer->effective_status,
                'url' => "/customers/{$customer->id}",
                'matched_field' => $this->matchedField($customer, ['company_name', 'first_name', 'last_name', 'email', 'phone'], $q),
            ];
        })->values()->all();
    }

    private function searchOpportunities(string $q, int $limit): array
    {
        return Opportunity::query()
            ->where(function ($sq) use ($q) {
                $sq->where('title', 'LIKE', "%{$q}%")
                    ->orWhere('notes', 'LIKE', "%{$q}%");
            })
            ->with('customer')
            ->limit($limit)
            ->get()
            ->map(function ($opp) use ($q) {
                return [
                    'id' => $opp->id,
                    'type' => 'opportunity',
                    'title' => $opp->title,
                    'subtitle' => implode(' — ', array_filter([$opp->customer?->display_name, $opp->stage])),
                    'status' => $opp->stage,
                    'url' => "/customers/{$opp->customer_id}/opportunities/{$opp->id}",
                    'matched_field' => $this->matchedField($opp, ['title', 'notes'], $q),
                ];
            })->values()->all();
    }

    private function matchedField($model, array $fields, string $q): string
    {
        foreach ($fields as $field) {
            if (stripos((string) ($model->$field ?? ''), $q) !== false) {
                return $field;
            }
        }

        // Fall back to JSON match
        if (stripos((string) json_encode($model->contacts ?? []), $q) !== false) {
            return 'contacts';
        }
        if (stripos((string) json_encode($model->addresses ?? []), $q) !== false) {
            return 'addresses';
        }

        return $fields[0];
    }
}
