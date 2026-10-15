<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('create-users') || $user->can('view-users');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('create-users') || $user->can('view-users');
    }

    public function create(User $user): bool
    {
        return $user->can('create-users');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('update-users');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('delete-users');
    }
}
