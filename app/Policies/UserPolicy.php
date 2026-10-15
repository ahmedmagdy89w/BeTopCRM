<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-users');
    }

    public function viewAssignable(User $user): bool
    {
        return $user->can('view-assignable-users') || $user->can('view-users');
    }

    public function view(User $user, User $target): bool
    {
        return $user->can('view-users') || $user->id === $target->id;
    }

    public function create(User $user): bool
    {
        return $user->can('create-users');
    }

    public function update(User $user, User $target): bool
    {
        return $user->can('update-users');
    }

    public function delete(User $user, User $target): bool
    {
        return $user->can('delete-users') && $user->id !== $target->id;
    }
}
