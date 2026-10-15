<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Users
            'create-users', 'update-users', 'delete-users',
            'view-users', 'view-assignable-users',

            // Customers (unified: individual|company, is_lead flag)
            'create-customers', 'update-customers', 'delete-customers',
            'view-customers',

            // Opportunities
            'create-opportunities', 'update-opportunities', 'delete-opportunities',
            'view-opportunities',

            // Quotations
            'create-quotations', 'view-quotations', 'send-quotations',

            // Invoices
            'create-invoices', 'view-invoices',

            // Activities
            'create-activities', 'update-activities', 'delete-activities',
            'view-activities',

            // Notes
            'view-notes',

            // Timeline
            'view-timeline',

            // Tasks
            'create-tasks', 'update-tasks', 'delete-tasks',
            'view-tasks', 'complete-tasks',

            // Tags
            'create-tags', 'update-tags', 'delete-tags',
            'view-tags',

            // Campaigns
            'create-campaigns', 'update-campaigns', 'delete-campaigns',
            'view-campaigns', 'send-campaigns',

            // Email Templates
            'create-templates', 'update-templates', 'delete-templates',

            // Attachments
            'create-attachments', 'update-attachments', 'delete-attachments',
            'view-attachments',

            // Custom Fields
            'create-custom-fields', 'update-custom-fields', 'delete-custom-fields',

            // Products
            'create-products', 'update-products', 'delete-products',
            'view-products',

            // Taxes
            'create-taxes', 'update-taxes', 'delete-taxes',
            'view-taxes',

            // Webhooks
            'create-webhooks', 'update-webhooks', 'delete-webhooks',
            'view-webhooks',

            // Misc (unchanged)
            'view-audit-log',
            'view-reports',
            'create-ai-chats',
            'import-data',
            'export-data',

            // Company Settings
            'view-company-settings', 'update-company-settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // ── Admin — all permissions ──────────────────────────────────────────
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($permissions);

        // ── Manager ─────────────────────────────────────────────────────────
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->syncPermissions(array_diff($permissions, [
            // Admin-only
            'create-users', 'update-users', 'delete-users', 'view-users',
            'create-custom-fields', 'update-custom-fields', 'delete-custom-fields',
            'create-webhooks', 'update-webhooks', 'delete-webhooks', 'view-webhooks',
            'view-audit-log',
            'update-company-settings',
        ]));

        // ── Sales Rep ────────────────────────────────────────────────────────
        $salesRep = Role::firstOrCreate(['name' => 'sales_rep']);
        $salesRep->syncPermissions([
            'view-assignable-users',
            'view-customers', 'create-customers', 'update-customers',
            'view-opportunities', 'create-opportunities', 'update-opportunities',
            'create-quotations', 'view-quotations', 'send-quotations',
            'create-invoices', 'view-invoices',
            'create-activities', 'update-activities',
            'view-activities', 'view-notes', 'view-timeline',
            'view-tasks', 'create-tasks', 'update-tasks', 'complete-tasks',
            'view-tags',
            'view-campaigns',
            'view-attachments', 'create-attachments', 'update-attachments',
            'view-products',
            'view-taxes',
            'export-data',
        ]);

        // ── Default admin user ───────────────────────────────────────────────
        $adminUser = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => 'Admin',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'locale' => 'en',
            ]
        );
        $adminUser->assignRole('admin');
    }
}
