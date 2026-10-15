<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserSettingsService
{
    public function show(User $user): User
    {
        return $user->loadCount(['assignedCustomers', 'managedCustomers']);
    }

    public function update(User $user, array $data): User
    {
        if (isset($data['notification_preferences'])) {
            $existing = $user->notification_preferences ?? [];
            $data['notification_preferences'] = array_merge($existing, $data['notification_preferences']);
        }

        $user->update($data);

        return $user->fresh();
    }

    public function uploadAvatar(User $user, UploadedFile $file): User
    {
        // Delete old avatar
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $file->store("avatars/{$user->id}", 'public');
        $user->update(['avatar_path' => $path]);

        return $user->fresh();
    }

    public function deleteAvatar(User $user): void
    {
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }
    }

    public function changePassword(User $user, string $password): void
    {
        $user->update(['password' => Hash::make($password)]);
    }
}
