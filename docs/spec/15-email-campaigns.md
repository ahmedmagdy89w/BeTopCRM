# 15 — Email Templates & Campaigns

Reusable email templates with placeholder support, plus a bulk campaign system for customer outreach.

---

## Database Tables

### `email_templates`

| Column | Type | Constraints |
|---|---|---|
| `id` | `bigIncrements` | PK |
| `name` | `string(255)` | required |
| `subject` | `string(255)` | required |
| `body` | `text` | required — HTML with placeholders |
| `placeholders` | `json` | auto-extracted list of placeholders (read-only) |
| `created_by` | `foreignId → users` | required, `constrained()->cascadeOnDelete()` |
| `created_at` | `timestamp` | auto |
| `updated_at` | `timestamp` | auto |

#### Placeholder Syntax

Placeholders use double curly braces: `{{contact_name}}`, `{{company_name}}`, `{{deal_value}}`.

Available placeholders (auto-resolved at send time):

| Placeholder | Resolves To |
|---|---|
| `{{contact_name}}` | Customer's `contact_name` |
| `{{contact_email}}` | Customer's `contact_email` |
| `{{company_name}}` | Customer's `company_name` |
| `{{account_manager_name}}` | Account manager's `name` |
| `{{deal_value}}` | Compatibility placeholder for the customer's total won Opportunity value |
| `{{custom_*}}` | Any custom field passed in campaign data |

### `campaigns`

| Column | Type | Constraints |
|---|---|---|
| `id` | `bigIncrements` | PK |
| `name` | `string(255)` | required |
| `email_template_id` | `foreignId → email_templates` | required, `constrained()->cascadeOnDelete()` |
| `status` | `string(30)` | default `draft` |
| `segment_filters` | `json` | nullable — customer filter criteria |
| `scheduled_at` | `datetime` | nullable |
| `sent_at` | `datetime` | nullable |
| `created_by` | `foreignId → users` | required, `constrained()->cascadeOnDelete()` |
| `created_at` | `timestamp` | auto |
| `updated_at` | `timestamp` | auto |

#### Campaign Status Values

| Status | Description |
|---|---|
| `draft` | Being created, not yet finalized |
| `scheduled` | Scheduled for future send |
| `sending` | Currently being processed (queued) |
| `sent` | All emails dispatched |
| `cancelled` | Cancelled before or during sending |

#### Segment Filters

JSON object matching customer filter format — campaigns target a segment of customers.

```json
{
  "status": "active",
  "health_below": 60,
  "organization_id": null,
  "tag": "enterprise"
}
```

### `campaign_recipients`

| Column | Type | Constraints |
|---|---|---|
| `id` | `bigIncrements` | PK |
| `campaign_id` | `foreignId → campaigns` | required, `constrained()->cascadeOnDelete()` |
| `customer_id` | `foreignId → customers` | required, `constrained()->cascadeOnDelete()` |
| `email` | `string(255)` | required — snapshot of email at send time |
| `status` | `string(20)` | default `pending` |
| `sent_at` | `datetime` | nullable |
| `failed_reason` | `text` | nullable |
| `created_at` | `timestamp` | auto |
| `updated_at` | `timestamp` | auto |

**Unique:** `(campaign_id, customer_id)` — one email per customer per campaign.

#### Recipient Status Values

`pending`, `sent`, `failed`

---

## Models

### EmailTemplate

**File:** `app/Models/EmailTemplate.php`

```php
class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'subject', 'body', 'placeholders', 'created_by'];

    protected $casts = [
        'placeholders' => 'array',
    ];

    // Relationships
    public function creator(): BelongsTo     // → User
    public function campaigns(): HasMany     // → Campaign

    // Auto-extract placeholders from body on save
    protected static function booted(): void
    {
        static::saving(function (self $template) {
            preg_match_all('/\{\{(\w+)\}\}/', $template->body, $matches);
            $template->placeholders = array_unique($matches[1]);
        });
    }
}
```

### Campaign

**File:** `app/Models/Campaign.php`

```php
class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'email_template_id', 'status',
        'segment_filters', 'scheduled_at', 'created_by',
    ];

    protected $casts = [
        'segment_filters' => 'array',
        'scheduled_at'    => 'datetime',
        'sent_at'         => 'datetime',
    ];

    const STATUSES = ['draft', 'scheduled', 'sending', 'sent', 'cancelled'];

    // Relationships
    public function template(): BelongsTo              // → EmailTemplate
    public function recipients(): HasMany              // → CampaignRecipient
    public function creator(): BelongsTo               // → User

    // Helpers
    public function recipientCount(): int
    public function sentCount(): int
    public function failedCount(): int
}
```

### CampaignRecipient

**File:** `app/Models/CampaignRecipient.php`

```php
class CampaignRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id', 'customer_id', 'email',
        'status', 'sent_at', 'failed_reason',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    // Relationships
    public function campaign(): BelongsTo  // → Campaign
    public function customer(): BelongsTo  // → Customer
}
```

---

## API Endpoints

### Email Templates

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/email-templates` | `EmailTemplateController@index` | List all templates |
| `POST` | `/email-templates` | `EmailTemplateController@store` | Create a template |
| `GET` | `/email-templates/{template}` | `EmailTemplateController@show` | Show with extracted placeholders |
| `PUT` | `/email-templates/{template}` | `EmailTemplateController@update` | Update template |
| `DELETE` | `/email-templates/{template}` | `EmailTemplateController@destroy` | Delete template |
| `POST` | `/email-templates/{template}/preview` | `EmailTemplateController@preview` | Preview with sample data |

### Campaigns

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/campaigns` | `CampaignController@index` | List campaigns |
| `POST` | `/campaigns` | `CampaignController@store` | Create campaign (draft) |
| `GET` | `/campaigns/{campaign}` | `CampaignController@show` | Show campaign with stats |
| `PUT` | `/campaigns/{campaign}` | `CampaignController@update` | Update (only while draft) |
| `DELETE` | `/campaigns/{campaign}` | `CampaignController@destroy` | Delete (only while draft) |
| `POST` | `/campaigns/{campaign}/send` | `CampaignController@send` | Send campaign now (queued) |
| `POST` | `/campaigns/{campaign}/schedule` | `CampaignController@schedule` | Schedule for later |
| `POST` | `/campaigns/{campaign}/cancel` | `CampaignController@cancel` | Cancel (if draft/scheduled) |
| `GET` | `/campaigns/{campaign}/recipients` | `CampaignController@recipients` | List recipients with status |

---

## Validation

### StoreEmailTemplateRequest

```php
public function rules(): array
{
    return [
        'name'    => ['required', 'string', 'max:255'],
        'subject' => ['required', 'string', 'max:255'],
        'body'    => ['required', 'string'],
    ];
}
```

### StoreCampaignRequest

```php
public function rules(): array
{
    return [
        'name'              => ['required', 'string', 'max:255'],
        'email_template_id' => ['required', 'exists:email_templates,id'],
        'segment_filters'   => ['nullable', 'array'],
        'segment_filters.status'          => ['nullable', 'string'],
        'segment_filters.health_below'    => ['nullable', 'integer', 'min:0', 'max:100'],
        'segment_filters.organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        'segment_filters.tag'             => ['nullable', 'string'],
    ];
}
```

### ScheduleCampaignRequest

```php
public function rules(): array
{
    return [
        'scheduled_at' => ['required', 'date', 'after:now'],
    ];
}
```

---

## Resources

### EmailTemplateResource

```json
{
  "id": 1,
  "name": "Welcome Email",
  "subject": "Welcome to our platform, {{contact_name}}!",
  "body": "<h1>Hello {{contact_name}}</h1><p>We're thrilled to have {{company_name}} on board...</p>",
  "placeholders": ["contact_name", "company_name"],
  "created_at": "2026-03-08T10:00:00Z"
}
```

### CampaignResource

```json
{
  "id": 1,
  "name": "Q1 Re-engagement",
  "template": {
    "id": 1,
    "name": "Welcome Email"
  },
  "status": "sent",
  "segment_filters": {
    "status": "at_risk",
    "health_below": 50
  },
  "stats": {
    "total_recipients": 45,
    "sent": 43,
    "failed": 2,
    "pending": 0
  },
  "scheduled_at": null,
  "sent_at": "2026-03-08T12:00:00Z",
  "created_at": "2026-03-08T10:00:00Z"
}
```

---

## CampaignService

**File:** `app/Services/CampaignService.php`

| Method | Signature | Description |
|---|---|---|
| `list` | `list(array $filters): LengthAwarePaginator` | Paginated campaigns |
| `create` | `create(array $data, User $user): Campaign` | Create draft campaign |
| `update` | `update(Campaign $campaign, array $data): Campaign` | Update (draft only) |
| `delete` | `delete(Campaign $campaign): void` | Delete (draft only) |
| `send` | `send(Campaign $campaign): void` | Resolve recipients, dispatch mail jobs |
| `schedule` | `schedule(Campaign $campaign, Carbon $at): Campaign` | Set scheduled_at, change status |
| `cancel` | `cancel(Campaign $campaign): Campaign` | Cancel if not already sent |

### Business Rules

- **send()** flow:
  1. Query customers matching `segment_filters`.
  2. Create `CampaignRecipient` rows for each with status `pending`.
  3. Set campaign status to `sending`.
  4. Dispatch `SendCampaignEmailJob` for each recipient (queued).
  5. Each job resolves placeholders, sends mail, updates recipient status.
  6. After all jobs complete, a chained job sets campaign status to `sent`.
- Only `draft` campaigns can be updated or deleted.
- Only `draft` or `scheduled` campaigns can be cancelled.
- Scheduled campaigns are picked up by `crm:send-scheduled-campaigns` command.

### Queued Mail Job

**File:** `app/Jobs/SendCampaignEmailJob.php`

```php
class SendCampaignEmailJob implements ShouldQueue
{
    public function handle(): void
    {
        // 1. Resolve placeholders from customer data
        // 2. Send via Mail::to()
        // 3. Update CampaignRecipient status to 'sent' or 'failed'
    }

    public int $tries = 3;
    public int $backoff = 60;
}
```

---

## Scheduled Command

### `crm:send-scheduled-campaigns`

**File:** `app/Console/Commands/SendScheduledCampaigns.php`

**Schedule:** Every minute (`everyMinute()`)

**Behavior:**
1. Query campaigns where `status = 'scheduled'` and `scheduled_at <= now()`.
2. For each, call `CampaignService::send()`.

---

## Permissions

| Permission | Admin | Manager | Sales Rep |
|---|---|---|---|
| `manage-templates` | ✅ | ✅ | ❌ |
| `manage-campaigns` | ✅ | ✅ | ❌ |
| `view-campaigns` | ✅ | ✅ | ✅ |
