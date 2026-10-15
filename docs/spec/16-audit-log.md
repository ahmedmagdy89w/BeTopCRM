# 16 — Audit Log

Automatic tracking of all create, update, and delete operations across CRM entities.

---

## Database Table: `audit_logs`

| Column | Type | Constraints |
|---|---|---|
| `id` | `bigIncrements` | PK |
| `user_id` | `foreignId → users` | nullable (null for system actions), `constrained()->nullOnDelete()` |
| `auditable_type` | `string` | polymorphic — model FQCN |
| `auditable_id` | `unsignedBigInteger` | polymorphic — record ID |
| `action` | `string(20)` | required — `created`, `updated`, `deleted` |
| `old_values` | `json` | nullable — previous values (for update/delete) |
| `new_values` | `json` | nullable — new values (for create/update) |
| `ip_address` | `string(45)` | nullable |
| `user_agent` | `string(255)` | nullable |
| `created_at` | `timestamp` | auto |

**Indexes:** `(auditable_type, auditable_id)`, `(user_id)`, `(action)`, `(created_at)`

> This table is append-only. No `updated_at` column. Records are never modified.

---

## Trait: `Auditable`

**File:** `app/Models/Traits/Auditable.php`

Applied to: `Organization`, `Lead`, `LeadActivity`, `Contact`, `Customer`, `CustomerActivity`, `Opportunity`, `Task`, `Tag`, `EmailTemplate`, `Campaign`.

```php
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            AuditLog::record($model, 'created');
        });

        static::updated(function (Model $model) {
            if ($model->wasChanged()) {
                AuditLog::record($model, 'updated');
            }
        });

        static::deleted(function (Model $model) {
            AuditLog::record($model, 'deleted');
        });
    }
}
```

---

## Model

**File:** `app/Models/AuditLog.php`

```php
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'auditable_type', 'auditable_id',
        'action', 'old_values', 'new_values',
        'ip_address', 'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo       // → User (nullable)
    public function auditable(): MorphTo    // → any audited model

    // Factory method called by the trait
    public static function record(Model $model, string $action): self
    {
        return static::create([
            'user_id'        => auth()->id(),
            'auditable_type' => $model->getMorphClass(),
            'auditable_id'   => $model->getKey(),
            'action'         => $action,
            'old_values'     => $action === 'created' ? null : $model->getOriginal(),
            'new_values'     => $action === 'deleted' ? null : $model->getAttributes(),
            'ip_address'     => request()->ip(),
            'user_agent'     => request()->userAgent(),
        ]);
    }

    // Scopes
    public function scopeForEntity(Builder $q, string $type, int $id)
    public function scopeByUser(Builder $q, int $userId)
    public function scopeAction(Builder $q, string $action)
    public function scopeDateRange(Builder $q, ?string $from, ?string $to)
}
```

### Sensitive Fields

The `record()` method should strip sensitive fields before storing:

```php
private static array $hiddenFields = ['password', 'remember_token', 'two_factor_secret'];
```

---

## API Endpoints

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/audit-logs` | `AuditLogController@index` | Global audit log (admin only) |
| `GET` | `/audit-logs/entity/{type}/{id}` | `AuditLogController@forEntity` | Audit history for a specific entity |

### Query Parameters (index)

| Param | Type | Description |
|---|---|---|
| `user_id` | `int` | Filter by user who made the change |
| `entity_type` | `string` | Filter by entity type (e.g., `lead`, `customer`, `organization`) |
| `action` | `string` | Filter by action (`created`, `updated`, `deleted`) |
| `date_from` | `date` | Start of date range |
| `date_to` | `date` | End of date range |
| `per_page` | `int` | Items per page (default: 25, max: 100) |

---

## AuditLogResource

```json
{
  "id": 1,
  "user": {
    "id": 2,
    "name": "Sales Rep"
  },
  "entity_type": "lead",
  "entity_id": 5,
  "action": "updated",
  "changes": {
    "status": {
      "old": "qualified",
      "new": "proposal"
    },
    "estimated_value": {
      "old": "25000.00",
      "new": "30000.00"
    }
  },
  "ip_address": "192.168.1.100",
  "created_at": "2026-03-08T14:30:00Z"
}
```

For `updated` actions, the resource computes a `changes` diff (only showing fields that changed) from `old_values` and `new_values`.

---

## AuditLogService

**File:** `app/Services/AuditLogService.php`

| Method | Signature | Description |
|---|---|---|
| `list` | `list(array $filters): LengthAwarePaginator` | Global log with filters |
| `forEntity` | `forEntity(string $type, int $id): LengthAwarePaginator` | History for one entity |

---

## Permissions

| Method | Rule |
|---|---|
| Global `index` | Has `view-audit-logs` (admin only) |
| `forEntity` | Can view the parent entity (uses entity's own policy) |

---

## Cleanup

For long-running systems, consider a scheduled command to prune old audit logs:

### `crm:prune-audit-logs`

**File:** `app/Console/Commands/PruneAuditLogs.php`

**Schedule:** Weekly

**Behavior:** Delete audit logs older than a configurable retention period (default: 365 days). Configurable via `config/crm.php`:

```php
'audit_log_retention_days' => env('AUDIT_LOG_RETENTION_DAYS', 365),
```
