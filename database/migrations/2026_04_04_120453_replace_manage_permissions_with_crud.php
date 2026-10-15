<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private array $newPermissions = [
        'create-users', 'update-users', 'delete-users',
        'create-organizations', 'update-organizations', 'delete-organizations',
        'create-leads', 'update-leads', 'delete-leads',
        'create-contacts', 'update-contacts', 'delete-contacts',
        'create-customers', 'update-customers', 'delete-customers',
        'create-activities', 'update-activities', 'delete-activities',
        'create-deals', 'update-deals', 'delete-deals',
        'create-tasks', 'update-tasks', 'delete-tasks', 'complete-tasks',
        'create-tags', 'update-tags', 'delete-tags',
        'create-campaigns', 'update-campaigns', 'delete-campaigns', 'send-campaigns',
        'create-templates', 'update-templates', 'delete-templates',
        'create-attachments', 'update-attachments', 'delete-attachments',
        'create-custom-fields', 'update-custom-fields', 'delete-custom-fields',
        'create-products', 'update-products', 'delete-products',
        'create-webhooks', 'update-webhooks', 'delete-webhooks', 'view-webhooks',
        'create-web-forms', 'update-web-forms', 'delete-web-forms',
    ];

    private array $oldPermissions = [
        'manage-users',
        'manage-organizations',
        'manage-leads',
        'manage-contacts',
        'manage-customers',
        'manage-activities',
        'manage-deals',
        'manage-tasks',
        'manage-tags',
        'manage-campaigns',
        'manage-templates',
        'manage-attachments',
        'manage-custom-fields',
        'manage-products',
        'manage-webhooks',
        'manage-web-forms',
        'manage-imports',
        'view-audit-logs',
    ];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->newPermissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $adminOnly = [
            'create-users', 'update-users', 'delete-users', 'view-users',
            'create-custom-fields', 'update-custom-fields', 'delete-custom-fields',
            'create-webhooks', 'update-webhooks', 'delete-webhooks', 'view-webhooks',
            'view-audit-log',
        ];

        $salesRepPermissions = [
            'view-organizations',
            'view-leads', 'create-leads', 'update-leads',
            'view-contacts',
            'view-customers',
            'create-activities', 'update-activities',
            'create-deals', 'update-deals',
            'view-tasks', 'create-tasks', 'update-tasks', 'complete-tasks',
            'view-tags',
            'view-campaigns',
            'view-attachments', 'create-attachments', 'update-attachments',
            'view-products',
            'view-web-forms',
            'export-data',
        ];

        // Compute all non-legacy permissions for admin and manager
        $allPermissions = Permission::pluck('name')
            ->reject(fn ($p) => in_array($p, $this->oldPermissions))
            ->values()
            ->all();

        $admin = Role::where('name', 'admin')->first();
        $admin?->syncPermissions($allPermissions);

        $manager = Role::where('name', 'manager')->first();
        $manager?->syncPermissions(array_diff($allPermissions, $adminOnly));

        $salesRep = Role::where('name', 'sales_rep')->first();
        $salesRep?->syncPermissions($salesRepPermissions);

        Permission::whereIn('name', $this->oldPermissions)->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No-op — restoring requires re-running the original seeder
    }
};
