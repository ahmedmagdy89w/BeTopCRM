<?php

namespace App\Services;

use App\Filters\CustomerFilter;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\User;
use App\Notifications\LeadAssignedNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function __construct(
        private WebhookDispatcher $webhookDispatcher,
        private LeadScoringService $leadScoringService,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        $query = Customer::with(['assignee', 'creator', 'accountManager', 'tags'])
            ->withCount(['activities', 'opportunities']);

        $query = (new CustomerFilter($filters))->apply($query);

        $allowedSort = ['company_name', 'first_name', 'status', 'lead_status', 'health_score', 'converted_at', 'contract_value', 'created_at', 'score'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSort) ? $filters['sort_by'] : 'created_at';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDir)
            ->paginate(min((int) ($filters['per_page'] ?? 15), 100));
    }

    public function create(array $data, User $user): Customer
    {
        $tagIds = $data['tag_ids'] ?? null;
        unset($data['tag_ids']);

        $data = $this->syncContactSnapshot($data);

        $data['created_by'] = $user->id;
        $data['is_lead'] = $data['is_lead'] ?? true;
        if ($data['is_lead'] && empty($data['lead_status'])) {
            $data['lead_status'] = 'new';
        }
        if (! $data['is_lead']) {
            $data['converted_at'] = $data['converted_at'] ?? now();
            $data['status'] = $data['status'] ?? 'active';
        }

        $customer = Customer::create($data);

        if ($tagIds !== null) {
            $customer->syncTags($tagIds);
        }

        if ($customer->is_lead) {
            $this->leadScoringService->calculate($customer);
        }
        $this->webhookDispatcher->dispatch('customer.created', $customer->fresh()->toArray());

        return $customer->fresh();
    }

    public function update(Customer $customer, array $data): Customer
    {
        $tagIds = $data['tag_ids'] ?? null;
        unset($data['tag_ids']);

        // Only re-derive when contacts are part of this payload (partial updates keep the snapshot).
        if (array_key_exists('contacts', $data)) {
            $data = $this->syncContactSnapshot($data);
        }

        $customer->update($data);

        if ($tagIds !== null) {
            $customer->syncTags($tagIds);
        }

        if ($customer->is_lead) {
            $this->leadScoringService->calculate($customer);
        }
        $this->webhookDispatcher->dispatch('customer.updated', $customer->fresh()->toArray());

        return $customer->fresh();
    }

    public function delete(Customer $customer): void
    {
        $this->webhookDispatcher->dispatch('customer.deleted', ['id' => $customer->id]);
        $customer->delete();
    }

    public function updateLeadStatus(Customer $customer, string $newStatus): Customer
    {
        if (! $customer->is_lead) {
            throw ValidationException::withMessages([
                'lead_status' => 'Only lead customers can transition lead status.',
            ]);
        }

        $allowed = Customer::LEAD_STATUS_TRANSITIONS[$customer->lead_status ?? 'new'] ?? [];
        if (! in_array($newStatus, $allowed)) {
            throw ValidationException::withMessages([
                'lead_status' => "Cannot transition from [{$customer->lead_status}] to [{$newStatus}].",
            ]);
        }

        $oldStatus = $customer->lead_status;
        $customer->update(['lead_status' => $newStatus]);

        $this->leadScoringService->calculate($customer);
        $this->webhookDispatcher->dispatch('customer.lead_status_changed', [
            'id' => $customer->id, 'old_status' => $oldStatus, 'new_status' => $newStatus,
        ]);

        return $customer->fresh();
    }

    public function assign(Customer $customer, int $userId): Customer
    {
        $customer->update(['assigned_to' => $userId]);
        $customer->refresh();

        if ($customer->assignee) {
            $customer->assignee->notify(new LeadAssignedNotification($customer));
        }

        $this->webhookDispatcher->dispatch('customer.assigned', ['id' => $customer->id, 'assigned_to' => $userId]);

        return $customer->load('assignee');
    }

    public function convertToCustomer(Customer $customer): Customer
    {
        if (! $customer->is_lead) {
            return $customer;
        }

        $customer->update([
            'is_lead' => false,
            'lead_status' => null,
            'status' => 'active',
            'converted_at' => now(),
            'last_contacted_at' => now(),
            'next_follow_up_at' => now()->addDays(7),
            'account_manager_id' => $customer->account_manager_id ?? $customer->assigned_to,
            'contract_value' => $customer->contract_value ?? $customer->estimated_value,
        ]);

        $this->webhookDispatcher->dispatch('customer.converted', $customer->fresh()->toArray());

        if ($customer->estimated_value && $customer->opportunities()->doesntExist()) {
            Opportunity::create([
                'customer_id' => $customer->id,
                'title' => "Initial opportunity — {$customer->display_name}",
                'type' => 'initial',
                'amount' => $customer->estimated_value,
                'stage' => 'won',
                'start_date' => now()->toDateString(),
                'created_by' => $customer->created_by ?? $customer->assigned_to ?? 1,
            ]);
            $this->syncContractValue($customer->fresh());
        }

        return $customer->fresh();
    }

    public function syncContractValue(Customer $customer): void
    {
        $total = $customer->opportunities()->where('stage', 'won')->sum('amount');
        $customer->update(['contract_value' => $total]);
    }

    /**
     * Mirror the primary (or first) contacts[] entry into the legacy
     * contact_name/contact_email/contact_phone snapshot columns.
     *
     * Explicitly provided snapshot values always win; an empty contacts
     * array clears the snapshot.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncContactSnapshot(array $data): array
    {
        $contacts = $data['contacts'] ?? null;

        if (! is_array($contacts)) {
            return $data;
        }

        $primary = null;

        foreach ($contacts as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $primary ??= $entry;

            if (! empty($entry['is_primary'])) {
                $primary = $entry;

                break;
            }
        }

        $data['contact_name'] ??= $primary ? trim(($primary['first_name'] ?? '').' '.($primary['last_name'] ?? '')) ?: null : null;
        $data['contact_email'] ??= $primary['email'] ?? null;
        $data['contact_phone'] ??= $primary['phone'] ?? null;

        return $data;
    }
}
