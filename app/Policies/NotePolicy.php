<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class NotePolicy
{
    private function deletePermission(Note $note): string
    {
        return match (class_basename($note->notable_type)) {
            'Customer' => 'delete-customers',
            'Opportunity' => 'delete-opportunities',
            default => 'delete-customers',
        };
    }

    private function updatePermission(Note $note): string
    {
        return match (class_basename($note->notable_type)) {
            'Customer' => 'update-customers',
            'Opportunity' => 'update-opportunities',
            default => 'update-customers',
        };
    }

    public function viewAny(User $user, Model $entity): bool
    {
        return $user->can('view-notes') && $user->can('view', $entity);
    }

    public function view(User $user, Note $note): bool
    {
        return $user->can('view-customers')
            || $user->can('view-opportunities')
            || $user->can($this->updatePermission($note));
    }

    public function create(User $user): bool
    {
        // Create is checked on the entity level in the controller
        return true;
    }

    public function update(User $user, Note $note): bool
    {
        return $note->user_id === $user->id;
    }

    public function delete(User $user, Note $note): bool
    {
        return $note->user_id === $user->id || $user->can($this->deletePermission($note));
    }

    public function togglePin(User $user, Note $note): bool
    {
        return $user->can($this->updatePermission($note));
    }
}
