<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleService
{
    /** System roles that cannot be deleted or renamed. */
    const SYSTEM_ROLES = ['admin', 'manager', 'sales_rep'];

    public function list(): Collection
    {
        return Role::with('permissions')->get();
    }

    public function permissions(): Collection
    {
        return Permission::orderBy('name')->get();
    }

    public function create(array $data): Role
    {
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);

        if (! empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return $role->load('permissions');
    }

    public function update(Role $role, array $data): Role
    {
        if (isset($data['name']) && ! in_array($role->name, self::SYSTEM_ROLES)) {
            $role->update(['name' => $data['name']]);
        }

        if (array_key_exists('permissions', $data)) {
            $role->syncPermissions($data['permissions'] ?? []);
        }

        return $role->fresh('permissions');
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }
}
