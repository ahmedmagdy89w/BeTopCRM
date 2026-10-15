<?php

namespace App\Services;

use App\Filters\CustomerFilter;
use App\Jobs\SendCampaignEmailJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class CampaignService
{
    public function __construct(private WebhookDispatcher $webhookDispatcher) {}

    public function list(array $filters): LengthAwarePaginator
    {
        $query = Campaign::with(['template:id,name', 'creator:id,name'])
            ->withCount('recipients')
            ->orderBy('created_at', 'desc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function create(array $data, User $user): Campaign
    {
        $data['created_by'] = $user->id;
        $data['status'] = 'draft';

        return Campaign::create($data);
    }

    public function update(Campaign $campaign, array $data): Campaign
    {
        $campaign->update($data);

        return $campaign->fresh(['template:id,name']);
    }

    public function delete(Campaign $campaign): void
    {
        if (! in_array($campaign->status, ['draft', 'cancelled'])) {
            throw ValidationException::withMessages([
                'status' => 'Only draft or cancelled campaigns can be deleted.',
            ]);
        }

        $campaign->delete();
    }

    public function send(Campaign $campaign): Campaign
    {
        if (! in_array($campaign->status, ['draft', 'scheduled'])) {
            throw ValidationException::withMessages([
                'status' => 'Campaign cannot be sent in its current status.',
            ]);
        }

        // Resolve recipients from segment filters
        $this->resolveRecipients($campaign);

        $campaign->update(['status' => 'sending']);

        // Dispatch individual email jobs
        $campaign->recipients()->where('status', 'pending')->each(function (CampaignRecipient $recipient) {
            SendCampaignEmailJob::dispatch($recipient);
        });

        $this->webhookDispatcher->dispatch('campaign.sent', [
            'id' => $campaign->id,
            'name' => $campaign->name,
        ]);

        return $campaign->fresh();
    }

    public function schedule(Campaign $campaign, string $scheduledAt): Campaign
    {
        if (! in_array($campaign->status, ['draft', 'scheduled'])) {
            throw ValidationException::withMessages([
                'status' => 'Only draft or scheduled campaigns can be scheduled.',
            ]);
        }

        $campaign->update(['status' => 'scheduled', 'scheduled_at' => $scheduledAt]);

        return $campaign->fresh();
    }

    public function cancel(Campaign $campaign): Campaign
    {
        if (! in_array($campaign->status, ['draft', 'scheduled'])) {
            throw ValidationException::withMessages([
                'status' => 'Only draft or scheduled campaigns can be cancelled.',
            ]);
        }

        $campaign->update(['status' => 'cancelled']);

        return $campaign->fresh();
    }

    public function recipients(Campaign $campaign, array $filters): LengthAwarePaginator
    {
        return $campaign->recipients()
            ->with('customer:id,type,first_name,last_name,company_name,contact_name')
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    private function resolveRecipients(Campaign $campaign): void
    {
        $filters = $campaign->segment_filters ?? [];

        $query = (new CustomerFilter($filters))->apply(Customer::query());

        $customers = $query->get()->filter(fn ($c) => $c->email ?? $c->contact_email);

        foreach ($customers as $customer) {
            CampaignRecipient::firstOrCreate(
                ['campaign_id' => $campaign->id, 'customer_id' => $customer->id],
                ['email' => $customer->email ?? $customer->contact_email, 'status' => 'pending']
            );
        }
    }
}
