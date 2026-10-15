<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookEndpoint;

class WebhookEndpointPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-webhooks');
    }

    public function view(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->can('view-webhooks');
    }

    public function create(User $user): bool
    {
        return $user->can('create-webhooks');
    }

    public function update(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->can('update-webhooks');
    }

    public function delete(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->can('delete-webhooks');
    }
}
