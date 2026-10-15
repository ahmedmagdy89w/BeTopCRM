# 21 — User Settings & Management

User profile settings, notification preferences, and admin user management with roles.

---

## Database Changes

### Add columns to `users` table

**Migration:** `add_settings_to_users_table`

| Column | Type | Constraints |
|---|---|---|
| `timezone` | `string(50)` | default `UTC` |
| `locale` | `string(10)` | default `en` |
| `date_format` | `string(20)` | default `Y-m-d` |
| `notification_preferences` | `json` | default `{}` |
| `avatar_path` | `string(500)` | nullable |
| `phone` | `string(30)` | nullable |
| `is_active` | `boolean` | default `true` |

---

## Notification Preferences

Stored as JSON on the user. Each notification type can be toggled on/off.

```json
{
  "lead_assigned": true,
  "lead_status_changed": true,
  "follow_up_reminder": true,
  "activity_reminder": true,
  "deal_expiring": true,
  "customer_health_alert": true,
  "task_due": true
}
```

Defaults: all `true`. Notifications are only sent if the user's preference for that type is `true`.

---

## API Endpoints

### User Settings (self-service)

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/settings` | `UserSettingsController@show` | Get my settings |
| `PUT` | `/settings` | `UserSettingsController@update` | Update my settings |
| `POST` | `/settings/avatar` | `UserSettingsController@uploadAvatar` | Upload profile photo |
| `DELETE` | `/settings/avatar` | `UserSettingsController@deleteAvatar` | Remove avatar |
| `PUT` | `/settings/password` | `UserSettingsController@changePassword` | Change password |

### User Management (admin)

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/users` | `UserController@index` | List all users (paginated) |
| `POST` | `/users` | `UserController@store` | Create new user with role |
| `GET` | `/users/{user}` | `UserController@show` | Show user details |
| `PUT` | `/users/{user}` | `UserController@update` | Update user (name, email, role, active status) |
| `DELETE` | `/users/{user}` | `UserController@destroy` | Deactivate user (soft — sets `is_active = false`) |
| `PUT` | `/users/{user}/role` | `UserController@updateRole` | Change a user's role |
| `POST` | `/users/{user}/activate` | `UserController@activate` | Re-activate a deactivated user |

### User Query Parameters (index)

| Param | Type | Description |
|---|---|---|
| `search` | `string` | Search by name, email |
| `role` | `string` | Filter by role (`admin`, `manager`, `sales_rep`) |
| `is_active` | `bool` | Filter active/inactive |
| `sort_by` | `string` | Column to sort (default: `name`) |
| `per_page` | `int` | Items per page (default: 15) |

---

## Validation

### UpdateSettingsRequest

```php
public function rules(): array
{
    return [
        'timezone'    => ['sometimes', 'string', 'timezone'],
        'locale'      => ['sometimes', 'string', Rule::in(['en', 'ar'])],
        'date_format' => ['sometimes', 'string', Rule::in(['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'd M Y'])],
        'phone'       => ['nullable', 'string', 'max:30'],
        'notification_preferences'                      => ['sometimes', 'array'],
        'notification_preferences.lead_assigned'         => ['boolean'],
        'notification_preferences.lead_status_changed'   => ['boolean'],
        'notification_preferences.follow_up_reminder'    => ['boolean'],
        'notification_preferences.activity_reminder'     => ['boolean'],
        'notification_preferences.deal_expiring'         => ['boolean'],
        'notification_preferences.customer_health_alert' => ['boolean'],
        'notification_preferences.task_due'              => ['boolean'],
    ];
}
```

### ChangePasswordRequest

```php
public function rules(): array
{
    return [
        'current_password' => ['required', 'current_password'],
        'password'         => ['required', 'string', 'min:8', 'confirmed'],
    ];
}
```

### UploadAvatarRequest

```php
public function rules(): array
{
    return [
        'avatar' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png'],  // 2MB
    ];
}
```

### StoreUserRequest (admin)

```php
public function rules(): array
{
    return [
        'name'     => ['required', 'string', 'max:255'],
        'email'    => ['required', 'email', 'unique:users,email'],
        'password' => ['required', 'string', 'min:8'],
        'role'     => ['required', 'string', Rule::in(['admin', 'manager', 'sales_rep'])],
        'phone'    => ['nullable', 'string', 'max:30'],
        'timezone' => ['sometimes', 'string', 'timezone'],
        'locale'   => ['sometimes', 'string', Rule::in(['en', 'ar'])],
    ];
}
```

### UpdateUserRequest (admin)

```php
public function rules(): array
{
    return [
        'name'      => ['sometimes', 'string', 'max:255'],
        'email'     => ['sometimes', 'email', Rule::unique('users')->ignore($this->user)],
        'role'      => ['sometimes', 'string', Rule::in(['admin', 'manager', 'sales_rep'])],
        'phone'     => ['nullable', 'string', 'max:30'],
        'timezone'  => ['sometimes', 'string', 'timezone'],
        'locale'    => ['sometimes', 'string', Rule::in(['en', 'ar'])],
        'is_active' => ['sometimes', 'boolean'],
    ];
}
```

---

## Resources

### UserSettingsResource

```json
{
  "id": 2,
  "name": "Jane Sales",
  "email": "jane@example.com",
  "phone": "+1-555-0123",
  "timezone": "America/New_York",
  "locale": "ar",
  "date_format": "m/d/Y",
  "notification_preferences": {
    "lead_assigned": true,
    "lead_status_changed": true,
    "follow_up_reminder": true,
    "deal_expiring": true,
    "customer_health_alert": true,
    "task_due": false
  },
  "avatar_url": "/storage/avatars/2/avatar.jpg",
  "role": "sales_rep",
  "created_at": "2026-01-15T10:00:00Z"
}
```

### UserResource (admin view)

```json
{
  "id": 2,
  "name": "Jane Sales",
  "email": "jane@example.com",
  "phone": "+1-555-0123",
  "timezone": "America/New_York",
  "locale": "ar",
  "role": "sales_rep",
  "is_active": true,
  "avatar_url": "/storage/avatars/2/avatar.jpg",
  "stats": {
    "leads_assigned": 45,
    "customers_managed": 12
  },
  "created_at": "2026-01-15T10:00:00Z",
  "updated_at": "2026-03-08T10:00:00Z"
}
```

---

## UserSettingsService

**File:** `app/Services/UserSettingsService.php`

| Method | Signature | Description |
|---|---|---|
| `show` | `show(User $user): User` | Return user with settings |
| `update` | `update(User $user, array $data): User` | Update settings |
| `uploadAvatar` | `uploadAvatar(User $user, UploadedFile $file): User` | Store avatar, update path |
| `deleteAvatar` | `deleteAvatar(User $user): void` | Remove avatar file + clear path |
| `changePassword` | `changePassword(User $user, string $password): void` | Hash and update password |

## UserService

**File:** `app/Services/UserService.php`

| Method | Signature | Description |
|---|---|---|
| `list` | `list(array $filters): LengthAwarePaginator` | All users with filters |
| `create` | `create(array $data): User` | Create user, assign role |
| `update` | `update(User $user, array $data): User` | Update user details |
| `updateRole` | `updateRole(User $user, string $role): User` | Sync role |
| `deactivate` | `deactivate(User $user): User` | Set `is_active = false`, revoke all tokens |
| `activate` | `activate(User $user): User` | Set `is_active = true` |

### Business Rules

- Deactivating a user revokes all their Sanctum tokens (forced logout).
- Deactivated users cannot log in — `LoginRequest` or auth middleware checks `is_active`.
- Admins cannot deactivate themselves.
- Role changes via `updateRole` use spatie's `syncRoles()`.
- Avatar stored under `storage/app/public/avatars/{user_id}/avatar.{ext}`.
- Notification preferences are merged on update (not replaced) — only provided keys are changed.
- `timezone` is used by notification scheduling to send reminders at the user's local 8 AM.
- `locale` controls the language of: validation error messages, API system messages, and notification email templates. Supported values: `en` (default) and `ar`.

---

## Locale Middleware

**File:** `app/Http/Middleware/SetLocale.php`

Registered in the `api` middleware group (runs on every authenticated API request).

```php
public function handle(Request $request, Closure $next): Response
{
    $locale = 'en';

    if ($user = $request->user()) {
        $locale = $user->locale ?? 'en';
    }

    // Allow frontend override via Accept-Language header (en / ar only)
    $header = $request->header('Accept-Language');
    if ($header && in_array($header, ['en', 'ar'])) {
        $locale = $header;
    }

    // Allow direct links (PDF preview/download iframes, signed public URLs)
    // to request a locale explicitly — browsers don't send the app's
    // Accept-Language header on plain navigation.
    $query = $request->query('lang');
    if ($query && in_array($query, ['en', 'ar'])) {
        $locale = $query;
    }

    App::setLocale($locale);
    return $next($request);
}
```

**Effect:**
- Laravel validation errors are returned in the user's language (Arabic translations provided by `laravel-lang/lang`).
- `__('messages.xxx')` calls resolve to the correct language file.
- System Mailable classes resolve the correct Blade template via `App::getLocale()`.
- Priority is explicit `?lang=` query, then `Accept-Language` header, then saved user locale, then English. Invalid values are ignored.

**Package dependency:** `laravel-lang/lang` + `laravel-lang/publisher`.
Install once with: `composer require laravel-lang/lang laravel-lang/publisher` then `php artisan lang:add ar`.

---

## Permissions

### UserSettingsController (self-service)

All endpoints require authentication only — users can manage their own settings.

### UserController (admin management)

| Permission | Admin | Manager | Sales Rep |
|---|---|---|---|
| `manage-users` | ✅ | ❌ | ❌ |
| `view-users` | ✅ | ✅ | ❌ |

Managers can view the user list (for assigning leads, reports) but cannot create/update/deactivate users.

---

## Auth Middleware Update

Add a check for `is_active` in the auth flow:

```php
// In a middleware or in LoginRequest
if (! $user->is_active) {
    throw ValidationException::withMessages([
        'email' => ['Your account has been deactivated.'],
    ]);
}
```
