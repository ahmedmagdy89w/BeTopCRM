<?php

namespace App\Policies;

use App\Models\CustomFieldDefinition;
use App\Models\User;

class CustomFieldDefinitionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CustomFieldDefinition $def): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('create-custom-fields');
    }

    public function update(User $user, CustomFieldDefinition $def): bool
    {
        return $user->can('update-custom-fields');
    }

    public function delete(User $user, CustomFieldDefinition $def): bool
    {
        return $user->can('delete-custom-fields');
    }
}
