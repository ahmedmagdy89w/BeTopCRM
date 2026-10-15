<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-tasks');
    }

    public function view(User $user, Task $task): bool
    {
        if ($user->can('update-tasks')) {
            return true;
        }

        return $task->user_id === $user->id || $task->created_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('create-tasks');
    }

    public function update(User $user, Task $task): bool
    {
        if ($user->can('update-tasks')) {
            return true;
        }

        return $task->user_id === $user->id || $task->created_by === $user->id;
    }

    public function delete(User $user, Task $task): bool
    {
        if ($user->can('delete-tasks')) {
            return true;
        }

        return $task->created_by === $user->id;
    }

    public function complete(User $user, Task $task): bool
    {
        return $user->can('complete-tasks') || $task->user_id === $user->id || $task->created_by === $user->id;
    }
}
