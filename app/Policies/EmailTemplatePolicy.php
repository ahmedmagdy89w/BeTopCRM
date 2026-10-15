<?php

namespace App\Policies;

use App\Models\EmailTemplate;
use App\Models\User;

class EmailTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-campaigns');
    }

    public function view(User $user, EmailTemplate $template): bool
    {
        return $user->can('view-campaigns');
    }

    public function create(User $user): bool
    {
        return $user->can('create-templates');
    }

    public function update(User $user, EmailTemplate $template): bool
    {
        return $user->can('update-templates');
    }

    public function delete(User $user, EmailTemplate $template): bool
    {
        return $user->can('delete-templates');
    }
}
