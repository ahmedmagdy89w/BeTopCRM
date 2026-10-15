<?php

namespace App\Services;

use App\Filters\OpportunityFilter;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpportunityService
{
    public function __construct(
        private CustomerService $customerService,
        private WebhookDispatcher $webhookDispatcher,
    ) {}

    public function list(?int $customerId = null, array $filters = []): LengthAwarePaginator
    {
        $query = Opportunity::with(['assignee', 'creator', 'customer', 'tags']);

        $query = (new OpportunityFilter($filters, $customerId))->apply($query);

        return $query->paginate(min((int) ($filters['per_page'] ?? 50), 100));
    }

    public function create(int $customerId, array $data, User $user): Opportunity
    {
        $data['customer_id'] = $customerId;
        $data['created_by'] = $user->id;
        $data['stage'] = $data['stage'] ?? 'prospecting';
        // Currency is settings-owned: always the company default.
        $data['currency'] = CompanySettingsService::defaultCurrency();

        $tagIds = $data['tag_ids'] ?? null;
        unset($data['tag_ids']);

        $opportunity = Opportunity::create($data);

        if ($tagIds !== null) {
            $opportunity->syncTags($tagIds);
        }

        $this->webhookDispatcher->dispatch('opportunity.created', $opportunity->fresh()->toArray());

        return $opportunity->fresh();
    }

    public function update(Opportunity $opportunity, array $data): Opportunity
    {
        $tagIds = $data['tag_ids'] ?? null;
        unset($data['tag_ids']);

        // Currency is settings-owned: never taken from request input.
        unset($data['currency']);

        $opportunity->update($data);

        if ($tagIds !== null) {
            $opportunity->syncTags($tagIds);
        }

        $this->webhookDispatcher->dispatch('opportunity.updated', $opportunity->fresh()->toArray());

        return $opportunity->fresh();
    }

    public function delete(Opportunity $opportunity): void
    {
        $this->webhookDispatcher->dispatch('opportunity.deleted', ['id' => $opportunity->id]);
        $opportunity->delete();
    }

    /**
     * Recalculate the opportunity amount from its attached products
     * (sum of pivot quantity × unit_price, net of line discounts).
     */
    public function recalculateAmount(Opportunity $opportunity): Opportunity
    {
        $total = DB::table('opportunity_products')
            ->where('opportunity_id', $opportunity->id)
            ->sum(DB::raw('quantity * unit_price - COALESCE(discount_amount, 0)'));

        $opportunity->update(['amount' => $total]);

        return $opportunity->fresh();
    }

    public function updateStage(Opportunity $opportunity, string $newStage): Opportunity
    {
        $allowed = Opportunity::STAGE_TRANSITIONS[$opportunity->stage] ?? [];
        if (! in_array($newStage, $allowed)) {
            throw ValidationException::withMessages([
                'stage' => "Cannot transition from [{$opportunity->stage}] to [{$newStage}].",
            ]);
        }

        $opportunity->update([
            'stage' => $newStage,
            'closed_at' => in_array($newStage, ['won', 'lost']) ? now() : null,
            'probability' => $newStage === 'won' ? 100 : ($newStage === 'lost' ? 0 : $opportunity->probability),
        ]);

        $this->webhookDispatcher->dispatch('opportunity.stage_changed', [
            'id' => $opportunity->id, 'new_stage' => $newStage,
        ]);

        if ($newStage === 'won') {
            $customer = $opportunity->customer;
            if ($customer && $customer->is_lead) {
                $this->customerService->convertToCustomer($customer);
            }
            if ($customer) {
                $this->customerService->syncContractValue($customer->fresh());
            }
        }

        return $opportunity->fresh();
    }
}
