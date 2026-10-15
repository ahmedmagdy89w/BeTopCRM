<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['create-opportunities', 'update-opportunities', 'delete-opportunities', 'view-opportunities'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->givePermissionTo(['create-opportunities', 'update-opportunities', 'delete-opportunities', 'view-opportunities']);
        }

        $manager = Role::where('name', 'manager')->first();
        if ($manager) {
            $manager->givePermissionTo(['create-opportunities', 'update-opportunities', 'delete-opportunities', 'view-opportunities']);
        }

        $salesRep = Role::where('name', 'sales_rep')->first();
        if ($salesRep) {
            $salesRep->givePermissionTo(['view-opportunities', 'create-opportunities', 'update-opportunities']);
        }

        // Ensure create/update customers permission covers new store endpoint for sales roles
        $salesRep?->givePermissionTo(['create-customers', 'update-customers']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Keep permissions on rollback (safe); only clear cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
