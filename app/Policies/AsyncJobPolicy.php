<?php

namespace App\Policies;

use App\Models\AsyncJob;
use App\Models\User;

class AsyncJobPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AsyncJob $asyncJob): bool
    {
        return $user->id === $asyncJob->user_id || $user->can('delete-users');
    }
}
