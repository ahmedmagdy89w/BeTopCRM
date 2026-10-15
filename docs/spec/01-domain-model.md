# 01 — Domain Model

> **Change #1 (unified customer):** `organizations`, `leads`, `contacts` (table), and `lead_activities` tables were removed. There is a single `customers` table with `type` (`individual`|`company`), `is_lead` flag (`true` = shown in Leads, `false` = shown in Customers), `lead_status` pipeline, JSON `contacts`/`addresses` (searchable via LIKE/JSON), plus a new `opportunities` table (`customer_id` → lead or customer). When an opportunity is marked `won` and the customer `is_lead`, it auto-converts to a real customer.

## Entity Relationship

```
Customer 1 ──── * Opportunity
Customer 1 ──── * CustomerActivity
Customer 1 ──── * CampaignRecipient
Opportunity 1 ──── * OpportunityActivity
Opportunity * ──── * Product (opportunity_products pivot: quantity, notes)

User 1 ──── * Customer (assigned_to)
User 1 ──── * Customer (created_by)
User 1 ──── * Customer (account_manager_id)
User 1 ──── * CustomerActivity (user_id)
User 1 ──── * Opportunity (assigned_to / created_by)
User 1 ──── * Task (user_id — assigned)
User 1 ──── * Task (created_by)
User 1 ──── * Notification (notifiable)
User 1 ──── * AuditLog (user_id)
User 1 ──── * Import (user_id)
User 1 ──── * Note (user_id)
User 1 ──── * WebhookEndpoint (created_by)

Attachment ── morphTo Customer | Opportunity (attachable)
Note ──── morphTo Customer | Opportunity (notable)
Note ──── 0..* Note (parent_id — self-referencing replies)
Task ──── morphTo Customer | Opportunity (taskable)
Tag  ──── morphToMany Customer | Opportunity (taggables)
AuditLog ── morphTo any auditable entity

EmailTemplate 1 ──── * Campaign
Campaign 1 ──── * CampaignRecipient

Product * ──── * Opportunity (opportunity_products pivot — contract line items)

ProductCategory 1 ──── * Product
ProductCategory 0..1 ──── * ProductCategory (parent_id — self-referencing hierarchy)

WebhookEndpoint 1 ──── * WebhookDelivery

CustomFieldGroup 1 ──── * CustomFieldDefinition (model or product-category scope)

User * ──── * Role (spatie/laravel-permission)
Role * ──── * Permission (spatie/laravel-permission)
```

---

## Tables

### `one_time_passwords`

Purpose-scoped OTP records used by password reset and future verification flows. Codes are stored as hashes and are never persisted in plaintext.

| Column | Type | Constraints |
|---|---|---|
| `id` | `bigIncrements` | PK |
| `purpose` | `string` | Scope such as `password_reset` or future OTP workflows |
| `identifier` | `string` | Recipient or subject identifier, indexed with `purpose` |
| `code_hash` | `string` | Hashed six-digit code |
| `attempts` | `unsignedTinyInteger` | Failed verification count, default `0` |
| `expires_at` | `timestamp` | Code expiry timestamp |
| `consumed_at` | `timestamp` | Nullable; set after successful use |
| `created_at` / `updated_at` | `timestamp` | Laravel timestamps |

---

### `customers` (unified — replaces organizations + leads + old customers)

| Column | Type | Constraints |
|---|---|---|
| `id` | `bigIncrements` | PK |
| `type` | `string(20)` | `individual` \| `company` |
| `is_lead` | `boolean` | default `true` — `true` = Leads list, `false` = Customers list |
| `lead_status` | `string(30)` | nullable — `new,contacted,qualified,proposal,negotiation,lost` (only when `is_lead`) |
| `status` | `string(30)` | customer lifecycle `active,at_risk,churned,paused,renewed` (when converted) |
| `assigned_to` / `created_by` / `account_manager_id` | `foreignId → users` | nullable |
| `source` | `string(50)` | nullable |
| `estimated_value` / `contract_value` | `decimal(12,2)` | nullable |
| `expected_close_date` / `birthday` | `date` | nullable |
| `first_name`, `last_name`, `email`, `phone`, `job_title`, `gender` | various | individual fields |
| `company_name`, `industry`, `website`, `tax_id` | various | company fields |
| `contacts` | `json` | `[{first_name,last_name,email,phone,title,is_primary}]` — searchable |
| `addresses` | `json` | `[{label,type,line_1,line_2,city,state,postal_code,country,is_primary}]` — searchable |
| `score`, `score_breakdown`, `scored_at` | `int/json/timestamp` | lead scoring (only when `is_lead`) |
| `health_score`, `last_contacted_at`, `next_follow_up_at`, `converted_at`, `notes` | various | lifecycle |
| `custom_fields` | `json` | nullable |
| `created_at`/`updated_at`/`deleted_at` | timestamps | soft delete |

**Indexes:** `type`, `is_lead`, `lead_status`, `status`.

#### Lead Status Values & Transitions (on `lead_status`)

| Status | Allowed Next Statuses |
|---|---|
| `new` | `contacted` |
| `contacted` | `qualified`, `lost` |
| `qualified` | `proposal`, `lost` |
| `proposal` | `negotiation`, `lost` |
| `negotiation` | `lost` (won happens via Opportunity) |
| `lost` | `new` _(re-open)_ |

### `opportunities` (new — replaces lead `won` pipeline)

| Column | Type | Constraints |
|---|---|---|
| `id` | `bigIncrements` | PK |
| `customer_id` | `foreignId → customers` | required, `cascadeOnDelete` |
| `title` | `string(255)` | required |
| `stage` | `string(30)` | `prospecting,qualification,proposal,negotiation,won,lost` |
| `amount` | `decimal(12,2)` | nullable |
| `currency` | `string(3)` | default `USD` |
| `probability` | `tinyInteger` | nullable 0–100 |
| `expected_close_date` | `date` | nullable |
| `type` | `string(30)` | nullable — `initial`, `upsell`, `cross_sell`, `renewal`, `downgrade` |
| `start_date` / `end_date` | `date` | nullable — contract period for won opportunities |
| `closed_at` | `timestamp` | nullable (set on won/lost) |
| `assigned_to` / `created_by` | `foreignId → users` | as labelled |
| `notes` / `custom_fields` | `text/json` | nullable |

**Rule:** `stage → won` + `customer.is_lead` ⇒ `customers.is_lead=false, status=active, converted_at=now()`. Won opportunity amounts are the source for customer contract value.

### `opportunity_activities`

Mirrors `customer_activities` for per-opportunity work logs (`opportunity_id`, `user_id`, `type` in `call,email,meeting,task,note`, `subject`, `description`, `outcome`, `due_date`, `completed_at`). Shown on the opportunity detail page and in its timeline.

### `opportunity_products` (pivot)

| Column | Constraints |
|---|---|
| `opportunity_id` → `opportunities` | `cascadeOnDelete` |
| `product_id` → `products` | `cascadeOnDelete` |
| `quantity` | default `1` |
| `unit_price` | nullable — custom price per opportunity, defaults to catalog price |
| `discount_percent` | nullable — when present it wins and derives `discount_amount` |
| `discount_amount` | default `0`, capped at line gross |
| `notes` | nullable |

Unique on (`opportunity_id`, `product_id`). Lines are returned in insertion order (`orderByPivot('id')`). Managed from the opportunity detail Products tab using invoice-style draft lines, editable prices prefilled from the catalog, per-line discount (%/amount toggle), tax badges, and a live subtotal/discount/tax/total footer.

### Removed tables

`organizations`, `contacts`, `leads`, `lead_activities`, `lead_products` — dropped (fresh start, no migration).

---

### `customer_activities`

Tracks every post-sale interaction: onboarding, support, check-ins, reviews, upsell conversations.

| Column | Type | Constraints |
|---|---|---|
| `id` | `bigIncrements` | PK |
| `customer_id` | `foreignId → customers` | required, `constrained()->cascadeOnDelete()` |
| `user_id` | `foreignId → users` | required, `constrained()->cascadeOnDelete()` |
| `type` | `string(30)` | required |
| `channel` | `string(30)` | nullable — `call,email,meeting,whatsapp,in_person,other` |
| `subject` | `string(255)` | required |
| `description` | `text` | nullable |
| `outcome` | `string(50)` | nullable |
| `due_date` | `datetime` | nullable — scheduled/planned follow-up time |
| `reminder_at` | `datetime` | nullable — exact time to notify the activity owner |
| `reminder_sent_at` | `datetime` | nullable — set once the reminder is claimed/sent |
| `completed_at` | `datetime` | nullable |
| `created_at` | `timestamp` | auto |
| `updated_at` | `timestamp` | auto |

#### Customer Activity Type Values

`onboarding`, `support`, `check_in`, `review`, `upsell`, `renewal`, `training`, `complaint`, `follow_up`, `whatsapp` (historic/WhatsApp logs), `general`

#### Contact Channel Values (optional)

`call`, `email`, `meeting`, `whatsapp`, `in_person`, `other`

Scheduling an incomplete `follow_up` activity syncs `customers.next_follow_up_at`; completing it recomputes the next upcoming follow-up. Rescheduling resets `reminder_sent_at` so the new reminder can fire.

#### Outcome Values (optional, set on completion)

`positive`, `neutral`, `negative`, `escalated`

---

The former `deals` and `deal_items` tables are removed by the Opportunity consolidation migration. Contract type and dates now belong to `opportunities`; amount, currency, stage, and product line items are stored on the Opportunity and its product pivot.

---

### `tasks`

See [13-tasks.md](13-tasks.md) for full spec. Polymorphic, linkable to leads, customers, organizations.

### `tags` + `taggables`

See [14-tags.md](14-tags.md) for full spec. Polymorphic tagging for leads, customers, organizations.

### `email_templates` + `campaigns` + `campaign_recipients`

See [15-email-campaigns.md](15-email-campaigns.md) for full spec.

### `audit_logs`

See [16-audit-log.md](16-audit-log.md) for full spec.

### `notifications`

Uses Laravel's built-in `notifications` table. See [12-notifications.md](12-notifications.md) for full spec.

### `attachments`

See [17-file-attachments.md](17-file-attachments.md) for full spec. Polymorphic file storage for customers and opportunities.

### `imports`

See [18-import-export.md](18-import-export.md) for full spec. Tracks CSV import job status.

### `users` (additional columns)

See [21-user-settings.md](21-user-settings.md) for `timezone`, `date_format`, `notification_preferences`, `avatar_path`, `phone`, `is_active` columns.

### `custom_field_definitions`

See [22-custom-fields.md](22-custom-fields.md) for full spec. Definitions belong to a group and are unique by `(group_id, name)`. The registry supports product, customer, and opportunity models; category-scoped groups are product-only.

### `custom_field_groups`

One model-scoped group per supported model and one independently scoped group per product category. `(entity_type, scope_key)` enforces uniqueness; `scope_key` is `entity` or `category:<id>` so MySQL's NULL uniqueness behavior cannot weaken the constraint.

### Entity `custom_fields` JSON column

Customers and opportunities store model-scoped values in `custom_fields`. Products store model-level values in `custom_fields` and category-level values in `category_custom_fields`. Product categories themselves do not store values. See [22-custom-fields.md](22-custom-fields.md).

### `customers` (scoring columns)

See [23-lead-scoring.md](23-lead-scoring.md) for `score`, `score_breakdown`, `scored_at` columns (only scored when `is_lead=true`).

### `products`

See [24-products-catalog.md](24-products-catalog.md) for full spec.

### `product_categories`

See [24-products-catalog.md](24-products-catalog.md) for full spec. Hierarchical categories with `parent_id` self-reference.

### `opportunity_products`

See [24-products-catalog.md](24-products-catalog.md) for the pivot table linking products to Opportunities.

### `taxes`

Simple rate table (`name` unique, `percent` 0–100, `is_active`, `created_by`). Products hold a nullable `tax_id`; quotation/invoice items snapshot `tax_id/name/percent` plus the computed `tax_amount` per line. See [24-products-catalog.md](24-products-catalog.md).

### `quotation_items` / `invoice_items` (snapshots)

Beyond product name/SKU/quantity/price/subtotal, each item snapshots `discount_percent` (nullable), `discount_amount`, `tax_id` (nullable, `nullOnDelete`), `tax_name`, `tax_percent`, and `tax_amount` — documents survive later product/tax edits.

### `webhook_endpoints` / `webhook_deliveries`

See [25-webhooks.md](25-webhooks.md) for full spec.

### `notes`

See [27-notes-comments.md](27-notes-comments.md) for full spec. Polymorphic threaded notes.

### `async_jobs`

Generic async job tracking table for polling-based background processing (reports, imports, etc.).

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint unsigned PK` | |
| `user_id` | `FK → users` | Job owner |
| `type` | `string(100)` | Service class (indexed) |
| `status` | `string(20)` | `pending`, `processing`, `completed`, `failed` (indexed, default `pending`) |
| `input` | `json nullable` | Serialised method + params |
| `result` | `json nullable` | Filled on completion |
| `error` | `text nullable` | Filled on failure |
| `progress` | `tinyint unsigned` | 0-100 (default 0) |
| `started_at` | `timestamp nullable` | |
| `completed_at` | `timestamp nullable` | |
| `created_at` | `timestamp` | |
| `updated_at` | `timestamp` | |

### Activity Timeline

See [28-activity-timeline.md](28-activity-timeline.md). Virtual read-only feed — no new table.
