<?php

namespace App\Services;

use App\Filters\CustomerActivityFilter;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\User;
use App\Notifications\CustomerHealthAlertNotification;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerActivityService
{
    public function list(Customer $customer, array $filters): LengthAwarePaginator
    {
        $query = CustomerActivity::query()
            ->where('customer_id', $customer->id)
            ->with('user');

        $query = (new CustomerActivityFilter($filters, $customer->id))->apply($query);

        return $query->paginate(min((int) ($filters['per_page'] ?? 15), 100));
    }

    public function globalList(array $filters, User $user): LengthAwarePaginator
    {
        $query = CustomerActivity::query()
            ->with(['user', 'customer'])
            ->whereHas('customer', fn ($customer) => $this->scopeViewableCustomers($customer, $user));

        if ($user->hasRole('sales_rep') && empty($filters['owner_id'])) {
            $query->where('customer_activities.user_id', $user->id);
        }

        $query = (new CustomerActivityFilter($filters))->apply($query);

        return $query->paginate(min((int) ($filters['per_page'] ?? 15), 100));
    }

    public function create(Customer $customer, array $data, User $user): CustomerActivity
    {
        $data['user_id'] = $user->id;
        $activity = $customer->activities()->create($data);
        $customer->update(['last_contacted_at' => now()]);
        $this->syncNextFollowUp($customer->fresh());

        return $activity->load('user');
    }

    public function update(CustomerActivity $activity, array $data): CustomerActivity
    {
        // Rescheduling an activity resets its pending reminder so the new time can fire.
        if (array_key_exists('due_date', $data) || array_key_exists('reminder_at', $data)) {
            $data['reminder_sent_at'] = null;
        }

        $activity->update($data);
        $this->syncNextFollowUp($activity->customer);

        return $activity->fresh('user');
    }

    public function delete(CustomerActivity $activity): void
    {
        $customer = $activity->customer;
        $activity->delete();
        $this->syncNextFollowUp($customer);
    }

    public function complete(CustomerActivity $activity, ?string $outcome): CustomerActivity
    {
        $activity->markCompleted($outcome);

        $customer = $activity->customer;
        $customer->update(['last_contacted_at' => now()]);
        $this->syncNextFollowUp($customer->fresh());

        // Adjust health score
        $delta = match ($outcome) {
            'positive' => +5,
            'negative', 'escalated' => -10,
            default => 0,
        };

        if ($delta !== 0) {
            $newScore = max(0, min(100, $customer->health_score + $delta));
            $customer->update(['health_score' => $newScore]);

            // Trigger alert if score drops below 40
            if ($newScore < 40 && $customer->accountManager) {
                $customer->accountManager->notify(new CustomerHealthAlertNotification($customer));
            }
        }

        return $activity->fresh('user');
    }

    /**
     * Keep the customer-level next follow-up in sync with the earliest
     * incomplete follow-up activity. Returns null when none is scheduled.
     */
    public function syncNextFollowUp(Customer $customer): void
    {
        $next = $customer->activities()
            ->where('type', 'follow_up')
            ->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->min('due_date');

        $customer->update(['next_follow_up_at' => $next]);
    }

    private function scopeViewableCustomers($query, User $user): void
    {
        // Mirrors CustomerPolicy::view: update-customers sees all, otherwise
        // only assigned / created / account-managed customers.
        if ($user->can('update-customers')) {
            return;
        }

        $query->where(function ($nested) use ($user): void {
            $nested->where('assigned_to', $user->id)
                ->orWhere('created_by', $user->id)
                ->orWhere('account_manager_id', $user->id);
        });
    }
}
