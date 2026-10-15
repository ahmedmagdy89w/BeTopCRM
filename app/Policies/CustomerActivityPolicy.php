<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\User;

class CustomerActivityPolicy
{
    public function viewAny(User $user, ?Customer $customer = null): bool
    {
        if ($customer === null) {
            return $user->can('view-activities');
        }

        return $user->can('view-activities') && $user->can('view', $customer);
    }

    public function create(User $user, Customer $customer): bool
    {
        return $user->can('view-customers');
    }

    public function update(User $user, CustomerActivity $activity): bool
    {
        return $activity->user_id === $user->id || $user->can('update-activities');
    }

    public function delete(User $user, CustomerActivity $activity): bool
    {
        return $activity->user_id === $user->id || $user->can('delete-activities');
    }

    public function complete(User $user, CustomerActivity $activity): bool
    {
        return $activity->user_id === $user->id || $user->can('update-activities');
    }
}
