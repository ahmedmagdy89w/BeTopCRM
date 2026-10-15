<?php

namespace App\Policies;

use App\Models\User;

class CompanySettingPolicy
{
    public function view(User $user): bool
    {
        return $user->can('view-company-settings');
    }

    public function update(User $user): bool
    {
        return $user->can('update-company-settings');
    }
}
