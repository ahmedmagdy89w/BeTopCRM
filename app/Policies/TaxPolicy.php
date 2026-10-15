<?php

namespace App\Policies;

use App\Models\Tax;
use App\Models\User;

class TaxPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-taxes');
    }

    public function view(User $user, Tax $tax): bool
    {
        return $user->can('view-taxes');
    }

    public function create(User $user): bool
    {
        return $user->can('create-taxes');
    }

    public function update(User $user, Tax $tax): bool
    {
        return $user->can('update-taxes');
    }

    public function delete(User $user, Tax $tax): bool
    {
        return $user->can('delete-taxes');
    }
}
