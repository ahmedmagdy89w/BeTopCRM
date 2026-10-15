<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // all authenticated users can view tags
    }

    public function view(User $user, Tag $tag): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('create-tags');
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->can('update-tags');
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->can('delete-tags');
    }
}
