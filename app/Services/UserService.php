<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function list(array $filters): LengthAwarePaginator
    {
        $query = User::query();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('name', 'LIKE', "%{$search}%")->orWhere('email', 'LIKE', "%{$search}%"));
        }

        if (isset($filters['role']) && $filters['role']) {
            $query->role($filters['role']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortBy = in_array($filters['sort_by'] ?? '', ['name', 'email', 'created_at']) ? $filters['sort_by'] : 'name';
        $query->orderBy($sortBy);

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function assignable(array $filters): LengthAwarePaginator
    {
        $query = User::query()->where('is_active', true);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('name', 'LIKE', "%{$search}%")->orWhere('email', 'LIKE', "%{$search}%"));
        }

        $perPage = (int) ($filters['per_page'] ?? 10);
        $perPage = max(1, min($perPage, 50));

        return $query->orderBy('name')->paginate($perPage);
    }

    public function findAssignable(int $id): User
    {
        return User::query()->where('is_active', true)->findOrFail($id);
    }

    public function create(array $data): User
    {
        $role = $data['role'];
        unset($data['role']);

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = true;

        $user = User::create($data);
        $user->assignRole($role);

        return $user->fresh();
    }

    public function update(User $user, array $data): User
    {
        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
            unset($data['role']);
        }

        $user->update($data);

        return $user->fresh();
    }

    public function updateRole(User $user, string $role): User
    {
        $user->syncRoles([$role]);

        return $user->fresh();
    }

    public function deactivate(User $user): User
    {
        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return $user->fresh();
    }

    public function activate(User $user): User
    {
        $user->update(['is_active' => true]);

        return $user->fresh();
    }
}
