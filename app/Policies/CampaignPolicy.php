<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-campaigns');
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->can('view-campaigns');
    }

    public function create(User $user): bool
    {
        return $user->can('create-campaigns');
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->can('update-campaigns');
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->can('delete-campaigns');
    }

    public function send(User $user, Campaign $campaign): bool
    {
        return $user->can('send-campaigns');
    }

    public function cancel(User $user, Campaign $campaign): bool
    {
        return $user->can('send-campaigns');
    }
}
