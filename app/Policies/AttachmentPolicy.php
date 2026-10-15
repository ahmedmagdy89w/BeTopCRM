<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AttachmentPolicy
{
    public function viewAny(User $user, Model $entity = null): bool
    {
        return true; // resolved via entity policy in controller
    }

    public function create(User $user): bool
    {
        return $user->can('create-attachments');
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        if ($attachment->uploaded_by === $user->id) {
            return true;
        }

        return $user->can('delete-attachments');
    }

    public function download(User $user, Attachment $attachment): bool
    {
        return $user->can('view-attachments');
    }
}
