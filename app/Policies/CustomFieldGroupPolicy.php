<?php

namespace App\Policies;

use App\Models\CustomFieldGroup;
use App\Models\User;

class CustomFieldGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CustomFieldGroup $group): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('create-custom-fields');
    }

    public function update(User $user, CustomFieldGroup $group): bool
    {
        return $user->can('update-custom-fields');
    }

    public function delete(User $user, CustomFieldGroup $group): bool
    {
        return $user->can('delete-custom-fields');
    }
}
