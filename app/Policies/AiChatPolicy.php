<?php

namespace App\Policies;

use App\Models\User;

class AiChatPolicy
{
    public function create(User $user): bool
    {
        return $user->can('create-ai-chats');
    }

    public function viewAny(User $user): bool
    {
        return $user->can('create-ai-chats');
    }
}
