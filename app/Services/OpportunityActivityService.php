<?php

namespace App\Services;

use App\Filters\OpportunityActivityFilter;
use App\Models\Opportunity;
use App\Models\OpportunityActivity;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class OpportunityActivityService
{
    public function list(Opportunity $opportunity, array $filters = []): LengthAwarePaginator
    {
        $query = OpportunityActivity::query()
            ->where('opportunity_id', $opportunity->id)
            ->with('user');

        $query = (new OpportunityActivityFilter($filters, $opportunity->id))->apply($query);

        return $query->paginate(min((int) ($filters['per_page'] ?? 15), 100));
    }

    public function create(Opportunity $opportunity, array $data, User $user): OpportunityActivity
    {
        $data['user_id'] = $user->id;
        $activity = $opportunity->activities()->create($data);

        if ($opportunity->customer) {
            $opportunity->customer->update(['last_contacted_at' => now()]);
        }

        return $activity->load('user');
    }

    public function update(OpportunityActivity $activity, array $data): OpportunityActivity
    {
        // Rescheduling an activity resets its pending reminder so the new time can fire.
        if (array_key_exists('due_date', $data) || array_key_exists('reminder_at', $data)) {
            $data['reminder_sent_at'] = null;
        }

        $activity->update($data);

        return $activity->fresh('user');
    }

    public function delete(OpportunityActivity $activity): void
    {
        $activity->delete();
    }

    public function complete(OpportunityActivity $activity, ?string $outcome): OpportunityActivity
    {
        $activity->markCompleted($outcome);

        return $activity->fresh('user');
    }
}
