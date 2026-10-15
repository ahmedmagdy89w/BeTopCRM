<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-customers');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('update-customers')
            || $customer->assigned_to === $user->id
            || $customer->created_by === $user->id
            || $customer->account_manager_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('create-customers') || $user->can('view-customers');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('update-customers')
            || $customer->assigned_to === $user->id
            || $customer->account_manager_id === $user->id;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('delete-customers');
    }
}
