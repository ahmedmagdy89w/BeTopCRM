# 03 — Eloquent Models

> Change #1: `Organization`, `Lead`, `LeadActivity`, `Contact` models removed. `Customer` is unified (`type`, `is_lead`, `lead_status`, JSON `contacts`/`addresses`, scoring). New `Opportunity` model holds the sales pipeline.

---

## Customer (unified)

**File:** `app/Models/Customer.php`

```php
class Customer extends Model
{
    use HasFactory, SoftDeletes, Auditable, HasTags, HasAttachments, HasCustomFields, HasNotes;

    const TYPES = ['individual', 'company'];
    const LEAD_STATUSES = ['new','contacted','qualified','proposal','negotiation','lost'];
    const LEAD_STATUS_TRANSITIONS = [
        'new' => ['contacted'],
        'contacted' => ['qualified', 'lost'],
        'qualified' => ['proposal', 'lost'],
        'proposal' => ['negotiation', 'lost'],
        'negotiation' => ['lost'],
        'lost' => ['new'],
    ];
    const CUSTOMER_STATUSES = ['active','at_risk','churned','paused','renewed'];
    const SOURCES = ['web','referral','cold_call','advertisement','social_media','trade_show','other','website'];

    // Relationships
    public function assignee(): BelongsTo       // → User (assigned_to)
    public function creator(): BelongsTo        // → User (created_by)
    public function accountManager(): BelongsTo // → User (account_manager_id)
    public function activities(): HasMany       // → CustomerActivity
    public function deals(): HasMany            // → Deal
    public function opportunities(): HasMany    // → Opportunity

    // Scopes
    public function scopeSearch(Builder $q, string $term)  // company_name, names, email, phone + contacts/addresses JSON LIKE
    public function scopeContactSearch(Builder $q, string $term)
    public function scopeAddressSearch(Builder $q, string $term)
    public function scopeOfType(Builder $q, string $type)
    public function scopeIsLead(Builder $q, bool $isLead)
    public function scopeLeadStatus(Builder $q, string $status)
    public function scopeStatus(Builder $q, string $status)
    public function scopeSource(Builder $q, string $source)
    public function scopeAssignedTo(Builder $q, int $userId)
}
```

---

## Opportunity

**File:** `app/Models/Opportunity.php`

```php
class Opportunity extends Model
{
    use HasFactory, Auditable, HasTags, HasAttachments, HasCustomFields, HasNotes;

    const STAGES = ['prospecting','qualification','proposal','negotiation','won','lost'];
    const STAGE_TRANSITIONS = [
        'prospecting' => ['qualification', 'lost'],
        'qualification' => ['prospecting', 'proposal', 'lost'],
        'proposal' => ['qualification', 'negotiation', 'lost'],
        'negotiation' => ['proposal', 'won', 'lost'],
        'won' => [],
        'lost' => ['prospecting'],
    ];

    // Relationships
    public function customer(): BelongsTo  // → Customer
    public function assignee(): BelongsTo  // → User (assigned_to)
    public function creator(): BelongsTo   // → User (created_by)
    public function products(): BelongsToMany // → Product through opportunity_products, orderByPivot('id')
}
```

---

## Tax

**File:** `app/Models/Tax.php`

```php
class Tax extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'percent', 'is_active', 'created_by'];

    protected $casts = [
        'percent'   => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function creator(): BelongsTo  // → User (created_by)
    public function products(): HasMany   // → Product
}
```

`Product` holds a nullable `tax_id` (`nullOnDelete`) plus a `tax(): BelongsTo` relation. Quotation/invoice items snapshot `discount_percent`, `discount_amount`, `tax_id/name/percent`, and `tax_amount` per line (see [24-products-catalog.md](24-products-catalog.md)).

---

## CustomerActivity

*(unchanged — now the sole activity model for lead-customers and customers)*

**File:** `app/Models/CustomerActivity.php`

```php
class CustomerActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'user_id', 'type', 'channel', 'subject',
        'description', 'outcome', 'due_date', 'reminder_at', 'reminder_sent_at', 'completed_at',
    ];

    protected $casts = [
        'due_date'         => 'datetime',
        'reminder_at'      => 'datetime',
        'reminder_sent_at' => 'datetime',
        'completed_at'     => 'datetime',
    ];

    const TYPES    = ['onboarding','support','check_in','review','upsell','renewal','training','complaint','follow_up','whatsapp','general'];
    const CHANNELS = ['call','email','meeting','whatsapp','in_person','other'];
    const OUTCOMES = ['positive','neutral','negative','escalated'];

    // Relationships
    public function customer(): BelongsTo  // → Customer
    public function user(): BelongsTo      // → User

    // Helpers
    public function isCompleted(): bool
    public function isOverdue(): bool
    public function isReminderDue(): bool
    public function markCompleted(?string $outcome = null): void
}
```

---

## Retired Deal Model

The former Deal model has been removed. Its contract attributes now belong to Opportunity; the legacy example below is retained only as historical reference.

**File:** `app/Models/Deal.php`

```php
class Deal extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'title', 'type', 'value', 'currency',
        'status', 'start_date', 'end_date', 'notes', 'created_by',
    ];

    protected $casts = [
        'value'      => 'decimal:2',
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    protected $attributes = [
        'currency' => 'USD',
        'status'   => 'active',
    ];

    const TYPES    = ['initial','upsell','cross_sell','renewal','downgrade'];
    const STATUSES = ['active','completed','cancelled','pending'];

    // Relationships
    public function customer(): BelongsTo  // → Customer
    public function creator(): BelongsTo   // → User (created_by)
}
```

---

## User (additions)

**File:** `app/Models/User.php` — add trait and relationships:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    // New relationships
    public function assignedCustomers(): HasMany   // → Customer (assigned_to)
    public function createdCustomers(): HasMany    // → Customer (created_by)
    public function managedCustomers(): HasMany       // → Customer (account_manager_id)
    public function customerActivities(): HasMany     // → CustomerActivity (user_id)
}
```

---

## AsyncJob

**File:** `app/Models/AsyncJob.php`

```php
class AsyncJob extends Model
{
    use HasFactory;

    const STATUS_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_FAILED     = 'failed';
    const STATUSES = ['pending', 'processing', 'completed', 'failed'];

    protected $fillable = [
        'user_id', 'type', 'status', 'input', 'result',
        'error', 'progress', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'input'        => 'array',
            'result'       => 'array',
            'progress'     => 'integer',
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // Relationships
    public function user(): BelongsTo  // → User

    // Status helpers
    public function isPending(): bool
    public function isProcessing(): bool
    public function isCompleted(): bool
    public function isFailed(): bool
    public function isFinished(): bool   // completed or failed
    public function markProcessing(): void
    public function markCompleted(array $result): void
    public function markFailed(string $error): void
    public function updateProgress(int $progress): void

    // Scopes
    public function scopeByType(Builder $query, string $type): Builder
    public function scopeByStatus(Builder $query, string $status): Builder
}
```
