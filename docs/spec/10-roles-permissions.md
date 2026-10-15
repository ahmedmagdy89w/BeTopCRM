# 10 — Roles & Permissions

Uses **spatie/laravel-permission v6**.

> **Note:** All `manage-*` permissions have been split into granular `create-*`, `update-*`, `delete-*` (and `view-*`) permissions. There are no `manage-*` permissions in the system.

---

## Roles

| Role | Description |
|---|---|
| `admin` | Full access to everything — manage users, settings, all data |
| `manager` | Full CRUD on most entities; cannot manage users, custom fields, or webhooks |
| `sales_rep` | Create and update own records only; no delete; no admin features |

---

## Permissions (69 total)

### Entity permissions

The current model uses unified customers and opportunities. The obsolete `organizations`, `leads`, and `contacts` permission groups are not registered by the seeder.

| Permission group | create | update | delete | view |
|---|---|---|---|---|
| customers | ✅ admin/manager/sales_rep | ✅ admin/manager/sales_rep | ✅ admin/manager | ✅ all roles |
| opportunities | ✅ admin/manager/sales_rep | ✅ admin/manager/sales_rep | ✅ admin/manager | ✅ all roles |
| quotations | ✅ all roles | — | — | ✅ all roles |
| invoices | ✅ all roles | — | — | ✅ all roles |
| activities | ✅ admin/manager/sales_rep | ✅ admin/manager/sales_rep | ✅ admin/manager | — |
| tasks | ✅ admin/manager/sales_rep | ✅ admin/manager/sales_rep | ✅ admin/manager | ✅ all roles |
| tags | ✅ admin/manager | ✅ admin/manager | ✅ admin/manager | ✅ all roles |
| campaigns | ✅ admin/manager | ✅ admin/manager | ✅ admin/manager | ✅ admin/manager |
| attachments | ✅ admin/manager/sales_rep | ✅ admin/manager/sales_rep | ✅ admin/manager | ✅ all roles |
| users | ✅ admin | ✅ admin | ✅ admin | ✅ admin |
| custom-fields | ✅ admin | ✅ admin | ✅ admin | — |
| webhooks | ✅ admin | ✅ admin | ✅ admin | ✅ admin |
| products | ✅ admin/manager | ✅ admin/manager | ✅ admin/manager | ✅ all roles |
| taxes | ✅ admin/manager | ✅ admin/manager | ✅ admin/manager | ✅ all roles |
| templates | ✅ admin/manager | ✅ admin/manager | ✅ admin/manager | — |

### Additional permissions

| Permission | Admin | Manager | Sales Rep |
|---|---|---|---|
| `send-quotations` | ✅ | ✅ | ✅ |
| `complete-tasks` | ✅ | ✅ | ✅ (own tasks) |
| `send-campaigns` | ✅ | ✅ | ❌ |
| `view-company-settings` | ✅ | ✅ | ❌ |
| `update-company-settings` | ✅ | ❌ | ❌ |
| `view-reports` | ✅ | ✅ | ❌ |
| `create-ai-chats` | ✅ | ✅ | ❌ |
| `view-audit-log` | ✅ | ❌ | ❌ |
| `export-data` | ✅ | ✅ | ✅ |
| `import-data` | ✅ | ✅ | ❌ |
| `view-assignable-users` | ✅ | ✅ | ✅ |

`view-quotations` and `view-invoices` authorize the global list policies. The combined frontend Sales Documents route is available when a user has either permission.

---


## Policies

### OrganizationPolicy

| Method | Rule |
|---|---|
| `viewAny` | Has `view-organizations` |
| `view` | Has `view-organizations` |
| `create` | Has `create-organizations` |
| `update` | Has `update-organizations` |
| `delete` | Has `delete-organizations` |

### LeadPolicy

| Method | Rule |
|---|---|
| `viewAny` | Has `view-leads` |
| `view` | Has `update-leads` OR is assigned to / created the lead |
| `create` | Has `create-leads` |
| `update` | Has `update-leads` OR is assigned to the lead |
| `delete` | Has `delete-leads` |
| `updateStatus` | Has `update-leads` OR is assigned to the lead |
| `assign` | Has `update-leads` |

### ContactPolicy

| Method | Rule |
|---|---|
| `viewAny` | Has `view-contacts` |
| `view` | Has `view-contacts` |
| `create` | Has `create-contacts` |
| `update` | Has `update-contacts` |
| `delete` | Has `delete-contacts` |

### LeadActivityPolicy

| Method | Rule |
|---|---|
| `viewAny` | Can view the parent lead |
| `create` | Has `create-activities` |
| `update` | Has `update-activities` AND created the activity |
| `delete` | Has `delete-activities` OR (has `delete-leads` AND created the activity) |

### CustomerPolicy

| Method | Rule |
|---|---|
| `viewAny` | Has `view-customers` |
| `view` | Has `update-customers` OR is the account manager |
| `create` | Has `create-customers` |
| `update` | Has `update-customers` |
| `delete` | Has `delete-customers` |

### CustomerActivityPolicy

| Method | Rule |
|---|---|
| `viewAny` | Has `view-activities` (global list); has `view-activities` AND can view the parent customer (nested) |
| `create` | Has `create-activities` |
| `update` | Has `update-activities` AND created the activity |
| `delete` | Has `delete-activities` OR (has `delete-customers` AND created the activity) |
| `complete` | Created the activity OR has `update-customers` |

### OpportunityPolicy

| Method | Rule |
|---|---|
| `viewAny` | Can view the parent customer |
| `create` | Has `create-opportunities` |
| `view` | Can view the parent customer |
| `update` | Has `update-opportunities` |
| `delete` | Has `delete-opportunities` |

### QuotationPolicy

| Method | Rule |
|---|---|
| `viewAny` | Feature enabled AND has `view-quotations`; used by global `GET /quotations` |
| `view` | Feature enabled AND (has `view-quotations` OR owns the opportunity assignment OR created the quotation) |
| `create` | Feature enabled AND has `create-quotations` |
| `send` | Feature enabled AND (has `send-quotations` OR owns the opportunity assignment) |

Denials for a disabled feature return `Response::deny(__('messages.quotations_disabled'))`.

### InvoicePolicy

| Method | Rule |
|---|---|
| `viewAny` | Feature enabled AND has `view-invoices`; used by global `GET /invoices` |
| `view` | Feature enabled AND (has `view-invoices` OR owns the opportunity assignment OR created the invoice) |
| `create` | Feature enabled AND has `create-invoices` |

Denials for a disabled feature return `Response::deny(__('messages.invoices_disabled'))`.

### TaxPolicy

| Method | Rule |
|---|---|
| `viewAny` | Has `view-taxes` |
| `view` | Has `view-taxes` |
| `create` | Has `create-taxes` |
| `update` | Has `update-taxes` |
| `delete` | Has `delete-taxes` |

### AttachmentPolicy

| Method | Rule |
|---|---|
| `view` | Has `view-attachments` |
| `create` | Has `create-attachments` |
| `update` | Has `update-attachments` |
| `delete` | Has `delete-attachments` OR created the attachment |

### ImportPolicy

| Method | Rule |
|---|---|
| `viewAny` | Has `import-data` |
| `create` | Has `import-data` |
| `view` | Created the import OR has `delete-users` (admin override) |

### UserPolicy

| Method | Rule |
|---|---|
| `viewAny` | Has `view-users` |
| `view` | Has `view-users` |
| `viewAssignable` | Has `view-assignable-users` |
| `create` | Has `create-users` |
| `update` | Has `update-users` |
| `delete` | Has `delete-users` |
| `updateRole` | Has `update-users` |

### RolePolicy

Applied to `Spatie\Permission\Models\Role`.

| Method | Rule |
|---|---|
| `viewAny` | Has `view-users` |
| `view` | Has `view-users` |
| `create` | Has `create-users` |
| `update` | Has `update-users` |
| `delete` | Has `delete-users` |

### AsyncJobPolicy

| Method | Rule |
|---|---|
| `viewAny` | Always `true` (query is scoped to own jobs in service) |
| `view` | Owns the job (`user_id` matches) OR has `delete-users` (admin override) |

### AiChatPolicy

Applied to the `App\AI\AiChat` value object (no database table — chat is stateless).

| Method | Rule |
|---|---|
| `viewAny` | Has `create-ai-chats` (suggested questions) |
| `create` | Has `create-ai-chats` (chat dispatch) |

---

---

## Custom Roles

In addition to the three system roles, admins can create **custom roles** at runtime via the API.

### Rules

- Custom roles are stored in the same `roles` table (spatie/laravel-permission).
- System roles (`admin`, `manager`, `sales_rep`) **cannot be deleted or renamed** — the `RoleService::SYSTEM_ROLES` constant enforces this.
- When creating or updating a user, the `role` field is validated against **all roles in the database** (not just the three system ones).
- The `GET /api/v1/permissions` endpoint returns the full list of available permission names to use when building a custom role.

### Implementation files

| File | Purpose |
|---|---|
| `app/Services/RoleService.php` | `list()`, `create()`, `update()`, `delete()`, `permissions()` |
| `app/Http/Controllers/Api/RoleController.php` | Thin controller delegating to `RoleService` |
| `app/Http/Requests/Role/StoreRoleRequest.php` | Validates `name` (unique) + `permissions[]` |
| `app/Http/Requests/Role/UpdateRoleRequest.php` | Same, with `sometimes` + current-record exclusion |
| `app/Http/Resources/Role/RoleResource.php` | Returns `id`, `name`, `is_system`, `permissions`, `users_count` |
| `app/Policies/RolePolicy.php` | `create/update/delete` require respective `*-users` permissions; `view` requires `view-users` |

---

## Middleware Usage

```php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('quotations', [QuotationController::class, 'globalIndex'])
        ->name('quotations.global');
    Route::get('invoices', [InvoiceController::class, 'globalIndex'])
        ->name('invoices.global');

    Route::middleware('permission:view-reports')->prefix('reports')->group(function () {
        // ...
    });
});
```

Controllers call `$this->authorize('action', $model)` to delegate to policies. The global quotation and invoice actions authorize `viewAny` on their model classes.

---

## Seeder

**File:** `database/seeders/RolesAndPermissionsSeeder.php`

```php
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

$permissions = [
    'create-users', 'update-users', 'delete-users', 'view-users',
    'create-customers', 'update-customers', 'delete-customers', 'view-customers',
    'create-opportunities', 'update-opportunities', 'delete-opportunities', 'view-opportunities',
    'create-quotations', 'view-quotations', 'send-quotations',
    'create-invoices', 'view-invoices',
    'create-activities', 'update-activities', 'delete-activities', 'view-activities',
    'view-notes', 'view-timeline',
    'create-tasks', 'update-tasks', 'delete-tasks', 'view-tasks', 'complete-tasks',
    'create-tags', 'update-tags', 'delete-tags', 'view-tags',
    'create-campaigns', 'update-campaigns', 'delete-campaigns', 'view-campaigns', 'send-campaigns',
    'create-templates', 'update-templates', 'delete-templates',
    'create-attachments', 'update-attachments', 'delete-attachments', 'view-attachments',
    'create-custom-fields', 'update-custom-fields', 'delete-custom-fields',
    'create-products', 'update-products', 'delete-products', 'view-products',
    'create-taxes', 'update-taxes', 'delete-taxes', 'view-taxes',
    'create-webhooks', 'update-webhooks', 'delete-webhooks', 'view-webhooks',
    'view-audit-log', 'view-reports', 'create-ai-chats', 'import-data', 'export-data',
    'view-company-settings', 'update-company-settings',
];

foreach ($permissions as $permission) {
    Permission::firstOrCreate(['name' => $permission]);
}

Role::firstOrCreate(['name' => 'admin'])->syncPermissions($permissions);

Role::firstOrCreate(['name' => 'manager'])->syncPermissions(array_diff($permissions, [
    'create-users', 'update-users', 'delete-users', 'view-users',
    'create-custom-fields', 'update-custom-fields', 'delete-custom-fields',
    'create-webhooks', 'update-webhooks', 'delete-webhooks', 'view-webhooks',
    'view-audit-log', 'update-company-settings',
]));

Role::firstOrCreate(['name' => 'sales_rep'])->syncPermissions([
    'view-customers', 'create-customers', 'update-customers',
    'view-opportunities', 'create-opportunities', 'update-opportunities',
    'create-quotations', 'view-quotations', 'send-quotations',
    'create-invoices', 'view-invoices',
    'create-activities', 'update-activities',
    'view-activities', 'view-notes', 'view-timeline',
    'view-tasks', 'create-tasks', 'update-tasks', 'complete-tasks',
    'view-tags', 'view-campaigns',
    'view-attachments', 'create-attachments', 'update-attachments',
    'view-products', 'view-taxes', 'export-data',
]);
```
