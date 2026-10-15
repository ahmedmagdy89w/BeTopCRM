<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;

class OpportunityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-opportunities');
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        return $user->can('update-opportunities')
            || $opportunity->assigned_to === $user->id
            || $opportunity->created_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('create-opportunities') || $user->can('view-opportunities');
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        return $user->can('update-opportunities')
            || $opportunity->assigned_to === $user->id;
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        return $user->can('delete-opportunities');
    }
}
