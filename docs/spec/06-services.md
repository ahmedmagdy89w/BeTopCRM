# 06 — Service Layer

---

## OneTimePasswordService

**File:** `app/Services/OneTimePasswordService.php`

Reusable purpose-scoped OTP infrastructure for password reset and future verification workflows. Codes are hashed, expire after the configured period, and are atomically consumed once.

| Method | Signature | Description |
|---|---|---|
| `issue` | `issue(string $identifier, string $purpose, int $expiresInMinutes = 10): string` | Generate and persist a six-digit OTP, replacing the prior OTP for the same purpose and identifier |
| `consume` | `consume(string $identifier, string $purpose, string $code, int $maxAttempts = 5): bool` | Verify, count failed attempts, and mark a valid OTP as consumed |

## PasswordResetService

**File:** `app/Services/PasswordResetService.php`

Uses `OneTimePasswordService` with the `password_reset` purpose, queues the OTP email, updates the password, and revokes existing Sanctum tokens after a successful reset.

---

## OrganizationService

**File:** `app/Services/OrganizationService.php`

### Methods

| Method | Signature | Description |
|---|---|---|
| `list` | `list(array $filters): LengthAwarePaginator` | Apply filters (search, industry), sort, paginate |
| `create` | `create(array $data, User $user): Organization` | Set `created_by` from authenticated user, create record |
| `update` | `update(Organization $org, array $data): Organization` | Update and return fresh |
| `delete` | `delete(Organization $org): void` | Soft-delete |

### Business Rules

- `created_by` is always set server-side from the authenticated user, never from input.
- Deleting an organization soft-deletes it; associated leads retain their `organization_id` (set to null via `nullOnDelete` FK).

---

## LeadService

**File:** `app/Services/LeadService.php`

### Methods

| Method | Signature | Description |
|---|---|---|
| `list` | `list(array $filters): LengthAwarePaginator` | Apply filters (search, status, source, assigned_to, organization_id), sort, paginate |
| `create` | `create(array $data, User $user): Lead` | Set `created_by`, default status `new` |
| `update` | `update(Lead $lead, array $data): Lead` | Update and return fresh |
| `delete` | `delete(Lead $lead): void` | Soft-delete |
| `updateStatus` | `updateStatus(Lead $lead, string $newStatus): Lead` | Validate transition, update, fire `LeadStatusChanged` event |
| `assign` | `assign(Lead $lead, int $userId): Lead` | Set `assigned_to` |

### Business Rules

- **Status transitions** are validated against `Lead::STATUS_TRANSITIONS` before persisting. Invalid transitions throw `ValidationException`.
- **`updateStatus`** fires `LeadStatusChanged` event after persisting.
- **When status becomes `won`**, automatically creates a `Customer` record via `CustomerService::createFromLead()`.
- **`created_by`** is always set server-side.
- **`assign`** validates the user exists (via request) and updates the FK.

---

## ReportService

**File:** `app/Services/ReportService.php`

### Methods

| Method | Signature | Description |
|---|---|---|
| `userPerformance` | `userPerformance(array $filters): array` | Aggregated metrics for one or all users |
| `companyReport` | `companyReport(array $filters): array` | Organization-level analytics |
| `customerReport` | `customerReport(array $filters): array` | Won-lead (customer) analytics |

### Business Rules

- All reports accept optional `date_from` and `date_to` filters to scope by time range.
- `userPerformance` can be filtered by `user_id` for a single user or returns all users.
- Only users with `view-reports` permission can access report endpoints.
- Aggregate queries use `DB::raw()` for performance; no N+1.

---

## AsyncJobService

**File:** `app/Services/AsyncJobService.php`

Generic reusable service for dispatching any service method as a queued background job with polling support.

### Methods

| Method | Signature | Description |
|---|---|---|
| `dispatch` | `dispatch(string $serviceClass, string $method, array $params, User $user): AsyncJob` | Create `async_jobs` record, dispatch `ProcessAsyncJob` to queue |
| `find` | `find(int $id): AsyncJob` | Find by ID or fail |
| `listForUser` | `listForUser(User $user, array $filters): LengthAwarePaginator` | User's own jobs, filterable by `type` and `status` |

### Business Rules

- `dispatch()` stores the fully-qualified service class in `type`, method name and params in `input` JSON.
- `ProcessAsyncJob` resolves the service from the container, calls the method, and writes result/error back.
- `listForUser()` always scopes to the authenticated user — users can only see their own jobs.
- Job status lifecycle: `pending → processing → completed` or `pending → processing → failed`.

---

## ReportExportService

**File:** `app/Services/ReportExportService.php`

Generates Excel/PDF report files to disk for async export. Injected with `ReportService`.

### Methods

| Method | Signature | Description |
|---|---|---|
| `exportUserPerformance` | `exportUserPerformance(array $filters, string $format, string $locale): array` | Generate user-performance file |
| `exportCompany` | `exportCompany(array $filters, string $format, string $locale): array` | Generate company report file |
| `exportCustomer` | `exportCustomer(array $filters, string $format, string $locale): array` | Generate customer report file |

### Business Rules

- Sets `App::setLocale($locale)` before generating so translated column headings work in queue context.
- Excel files use `Excel::store()` to write to `storage/app/private/exports/`.
- PDF files render via `ArabicPdfService::loadView()` (Arabic shaping + Cairo typeface) and store output to the same directory.
- Returns `['file_path' => '...', 'file_name' => '...', 'mime_type' => '...']`.
- Filenames include a random suffix to avoid collisions.

---

## CustomerService

**File:** `app/Services/CustomerService.php`

### Methods

| Method | Signature | Description |
|---|---|---|
| `list` | `list(array $filters): LengthAwarePaginator` | Apply filters (search, status, account_manager_id, organization_id), sort, paginate |
| `createFromLead` | `createFromLead(Lead $lead): Customer` | Auto-create customer from won lead (snapshots name, email, org) |
| `update` | `update(Customer $customer, array $data): Customer` | Update contract, status, account manager, notes |
| `delete` | `delete(Customer $customer): void` | Soft-delete |

### Business Rules

- **`createFromLead`** is called automatically by `LeadService::updateStatus()` when a lead becomes `won`.
- Snapshots `company_name` from organization (or lead name if no org), `contact_name` from lead full name, `contact_email` and `contact_phone` from lead.
- `account_manager_id` defaults to the lead's `assigned_to` user.
- `contract_value` defaults to lead's `estimated_value`.
- `converted_at` is set to `now()`.
- If a customer already exists for the lead (re-won after lost→new→won), returns the existing record instead of creating a duplicate.
- Auto-creates an initial won Opportunity (type `initial`) from the lead's `estimated_value` when conversion finds no existing opportunities.
- Sets `last_contacted_at` to `now()` and `next_follow_up_at` to 7 days from now.

---

## CustomerActivityService

**File:** `app/Services/CustomerActivityService.php`

### Methods

| Method | Signature | Description |
|---|---|---|
| `list` | `list(Customer $customer, array $filters): LengthAwarePaginator` | Activities for a customer, filterable by type, channel, status, dates, reminder, search |
| `globalList` | `globalList(array $filters, User $user): LengthAwarePaginator` | Activities across viewable customers with owner included; sales reps see own unless filtering |
| `create` | `create(Customer $customer, array $data, User $user): CustomerActivity` | Log a new post-sale activity, updates `last_contacted_at` and syncs `next_follow_up_at` |
| `update` | `update(CustomerActivity $activity, array $data): CustomerActivity` | Update and return fresh; rescheduling resets `reminder_sent_at` |
| `delete` | `delete(CustomerActivity $activity): void` | Delete activity and resync `next_follow_up_at` |
| `complete` | `complete(CustomerActivity $activity, ?string $outcome): CustomerActivity` | Set `completed_at` = now, optionally set outcome, update customer `last_contacted_at`, resync follow-up, adjust `health_score` |
| `syncNextFollowUp` | `syncNextFollowUp(Customer $customer): void` | Set `next_follow_up_at` to earliest incomplete `follow_up` due date (null when none) |

### Business Rules

- `user_id` is always set server-side from the authenticated user.
- Completing an activity with `positive` outcome increases customer `health_score` (+5, max 100).
- Completing with `negative` or `escalated` outcome decreases `health_score` (-10, min 0).
- Every create/complete operation updates the customer's `last_contacted_at` to `now()`.

---

## OpportunityActivityService

**File:** `app/Services/OpportunityActivityService.php`

Mirrors the per-opportunity feature set of `CustomerActivityService` (no global list, no follow-up sync, no health effects). Opportunity activity types stay `call, email, meeting, task, whatsapp, note`; existing rows are never rewritten.

### Methods

| Method | Signature | Description |
|---|---|---|
| `list` | `list(Opportunity $opportunity, array $filters = []): LengthAwarePaginator` | Activities for an opportunity, filterable by type, channel, status, dates, reminder, search |
| `create` | `create(Opportunity $opportunity, array $data, User $user): OpportunityActivity` | Log a new activity, updates parent customer `last_contacted_at` |
| `update` | `update(OpportunityActivity $activity, array $data): OpportunityActivity` | Update and return fresh; rescheduling resets `reminder_sent_at` |
| `delete` | `delete(OpportunityActivity $activity): void` | Delete activity |
| `complete` | `complete(OpportunityActivity $activity, ?string $outcome): OpportunityActivity` | Set `completed_at` = now, optionally set outcome |

### Business Rules

- `user_id` is always set server-side from the authenticated user.
- `reminder_at` must not be after `due_date` (validated on store and update).

---

## Retired DealService

DealService has been removed. OpportunityService owns contract lifecycle and amount aggregation.

**File:** `app/Services/DealService.php`

### Methods

| Method | Signature | Description |
|---|---|---|
| `list` | `list(Customer $customer): Collection` | All deals for a customer (not paginated — typically small set) |
| `create` | `create(Customer $customer, array $data, User $user): Deal` | Create deal with `created_by` set server-side |
| `show` | `show(Deal $deal): Deal` | Return deal |
| `update` | `update(Deal $deal, array $data): Deal` | Update and return fresh |
| `delete` | `delete(Deal $deal): void` | Delete deal |

### Business Rules

- `created_by` is always set server-side from the authenticated user.
- When a new deal is created, the customer's `contract_value` is automatically updated to the sum of all active deal values.

---

## QuotationService

**File:** `app/Services/QuotationService.php`

### Methods

| Method | Signature | Description |
|---|---|---|
| `list` | `list(array $filters): LengthAwarePaginator` | Return a filtered, sorted global quotation page with customer and opportunity summaries loaded |
| `create` | `create(Opportunity $opportunity, array $data, User $user): Quotation` | Snapshot opportunity product lines and create a draft quotation |
| `send` | `send(Quotation $quotation, User $user): Quotation` | Mark an unsent quotation as sent and record the sender/time |

### Listing Rules

- Search covers `quotation_number`, customer search fields, and opportunity title.
- `status` accepts one or more values; `customer_id`, `date_from`, and `date_to` are also supported.
- Date filtering uses the inclusive `valid_until` range.
- Allowed sorts are `created_at`, `quotation_number`, `valid_until`, `total`, and `status`.
- Pagination defaults to 15 and caps `per_page` at 100.

### Business Rules

- Creation is allowed only while the opportunity is open and requires at least one opportunity product.
- Each line snapshots product name, SKU, quantity, unit price, gross subtotal, `discount_percent` + derived `discount_amount`, and `tax_id/name/percent` + computed `tax_amount` inside a transaction. Lines keep insertion order.
- Totals come from `DocumentTotals::headers()` (sums of lines); there is no header-level tax/discount input. Header `tax_percent` is legacy and stored as `0`.
- Sending requires a customer email, is idempotent for an already-sent quotation, and records `sent_at` and `sent_by`.
- The quotation email (`QuotationMail`, queued) captures the sender's locale at dispatch and re-applies it when rendering, so the email body and attached PDF match the sender's UI language instead of the worker default.
- When quotations are disabled in Company Settings, `QuotationPolicy` denies all actions (see [30-company-settings.md](30-company-settings.md)).

---

## DocumentTotals

**File:** `app/Services/DocumentTotals.php` — pure static helper shared by quotations and invoices.

- `line(...)`: `gross = qty × price`; percent (when present and > 0) derives `amount = round(gross × pct/100, 2)`, else the amount is capped at gross; `tax = round((gross − amount) × rate/100, 2)`. Returns the full snapshot row including `discount_percent`.
- `headers(array $lines)`: `subtotal = Σ gross`, `discount_amount = Σ amounts`, `tax_amount = Σ taxes`, `total = subtotal − discount + tax`.

---

## ArabicPdfService

**File:** `app/Services/ArabicPdfService.php` — static wrapper around `Pdf::loadView()` used by **every** PDF call site (quotation/invoice download + preview, public signed downloads, quotation email attachment, all report exports).

### Methods

| Method | Signature | Description |
|---|---|---|
| `loadView` | `loadView(string $view, array $data = []): DomPdf` | Render, shape, and load a Blade view as a PDF document |
| `renderShapedHtml` | `renderShapedHtml(string $view, array $data = []): string` | Return the typography-injected, Arabic-shaped HTML (used by tests) |
| `containsArabic` | `containsArabic(?string $text): bool` | True when the text holds Arabic-script codepoints |
| `shapeText` | `shapeText(?string $text): string` | Shape one string into visual-order presentation forms |

### Business Rules

- dompdf cannot join Arabic letters or order them right-to-left (ضريبة renders as ةبيرض), so Arabic text nodes are pre-shaped with `khaled.alshamaa/ar-php` into visual-order presentation forms. Latin-only strings pass through untouched.
- Only pure Arabic runs are shaped: the splitter keeps whitespace, numbers, Latin, and punctuation as separate runs and reorders runs for RTL lines, because the library reverses digit separators (`1,250.00` would become `00.250,1`). Digits stay Western (`hindo=false`) so prices and quantities survive.
- Cairo (Regular 400 + Bold 700, shipped as static TTFs in `public/fonts/`, instantiated from the upstream variable font) is injected as the body typeface with DejaVu Sans fallback; dompdf caches generated metrics in `storage/fonts/` (writable at runtime, git-ignored).
- dompdf does not mirror table columns for `direction:rtl`, so quotation/invoice totals rows use `resources/views/pdf/partials/totals-row.blade.php`, which renders value-first in DOM (value left, label right) when `ar` and also flips the totals block alignment.

---

## InvoiceService

**File:** `app/Services/InvoiceService.php`

### Methods

| Method | Signature | Description |
|---|---|---|
| `list` | `list(array $filters): LengthAwarePaginator` | Return a filtered, sorted global invoice page with customer and opportunity summaries loaded |
| `create` | `create(Opportunity $opportunity, User $user): Invoice` | Create the single issued invoice for a won opportunity |

### Listing Rules

- Search covers `invoice_number`, customer search fields, and opportunity title.
- `status` accepts one or more values; `customer_id`, `date_from`, and `date_to` are also supported.
- Date filtering uses the inclusive `issued_at` range.
- Allowed sorts are `created_at`, `invoice_number`, `issued_at`, `total`, and `status`.
- Pagination defaults to 15 and caps `per_page` at 100.

### Business Rules

- Creation is allowed only for a won opportunity, and the unique `opportunity_id` constraint permits only one invoice.
- Line items are copied from the latest sent quotation (including its discount/tax snapshots), falling back to the latest quotation and then current opportunity products.
- The service sets `created_by` server-side and creates `InvoiceItem` snapshots inside a transaction.
- When invoices are disabled in Company Settings, `InvoicePolicy` denies all actions (see [30-company-settings.md](30-company-settings.md)).

---

## TaxService

**File:** `app/Services/TaxService.php`

| Method | Signature | Description |
|---|---|---|
| `list` | `list(bool $activeOnly = false): Collection` | Taxes ordered by name; `active_only` feeds product pickers |
| `create` | `create(array $data): Tax` | Create a tax rate |
| `update` | `update(Tax $tax, array $data): Tax` | Update name/percent/active flag |
| `delete` | `delete(Tax $tax): void` | Blocked with `messages.tax_delete_has_products` while products reference the tax |

---

## WhatsAppService

**File:** `app/Services/WhatsAppService.php`

### Responsibilities

- Normalize a customer's phone number using the first available value from the primary contact, customer phone, or contact phone.
- Prepare editable customer, quotation, and invoice messages. Document messages include temporary signed PDF links valid for seven days.
- Signed quotation/invoice URLs embed `lang` (the sender's UI locale inside the signature), so guests opening the public PDF see it in that language.
- Build Click-to-Chat `wa.me` URLs or send text with a URL preview through Meta's WhatsApp Cloud API when explicitly requested and configured.
- Record successful initiation as a completed `whatsapp` customer activity and, when supplied, an opportunity activity. Update the customer's `last_contacted_at` transactionally.

### Configuration and Delivery Semantics

- Click-to-Chat is the default and requires no WhatsApp SDK or server credentials. Opening WhatsApp does not prove that a user sent the message; the activity records initiation, not confirmed delivery.
- Optional Cloud API credentials are `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, and `WHATSAPP_API_VERSION` (default `v21.0`). The service also recognizes `whatsapp_access_token` and `whatsapp_phone_number_id` company-setting values if provisioned; no settings UI is provided for them.
- Cloud API requests send text only. A successful API response indicates acceptance by Meta, not delivery or read status. Inbound messages and status webhooks are not implemented.
- An activity is created after a successful Cloud API response, or after the frontend initiates Click-to-Chat and calls the CRM send endpoint.

See [32-whatsapp-messaging.md](32-whatsapp-messaging.md) for route contracts, authorization, and frontend behavior.

---

## EmailTemplateService

**File:** `app/Services/EmailTemplateService.php`

### Methods

| Method | Signature | Description |
|---|---|---|
| `list` | `list(array $filters): LengthAwarePaginator` | Filter by search, sort, paginate |
| `create` | `create(array $data, User $user): EmailTemplate` | Create template with `created_by` set server-side |
| `update` | `update(EmailTemplate $template, array $data): EmailTemplate` | Update and return fresh |
| `delete` | `delete(EmailTemplate $template): void` | Delete template |
| `preview` | `preview(EmailTemplate $template, array $variables): array` | Render subject/body with sample variable substitution |
| `generateWithAi` | `generateWithAi(array $data): array` | AI-generate a draft `{ subject, body, placeholders }` using `EmailTemplateGeneratorAgent` |

### Business Rules

- `created_by` is always set server-side.
- `generateWithAi` uses `EmailTemplateGeneratorAgent` (via `laravel/ai`). On failure it throws `ValidationException` with the `ai` key.
- The returned array has shape `['subject' => string, 'body' => string, 'placeholders' => string[]]` — it is **not** persisted automatically; the caller decides whether to save it.
- Placeholder names are extracted from `{{name}}` syntax in the generated body (same regex as model boot).

---

## AiChatService

**File:** `app/Services/AiChatService.php`

Stateless analytics chat — builds a grounded snapshot from existing services and prompts `CrmAnalyticsAgent` (via `laravel/ai`). See [34-ai-chat.md](34-ai-chat.md) for the snapshot layout and agent rules.

### Methods

| Method | Signature | Description |
|---|---|---|
| `answerWithAi` | `answerWithAi(array $data, int $userId): array` | Answer a question, returning `{ answer, sources, locale, generated_at }` |
| `suggestedQuestions` | `suggestedQuestions(string $locale = 'en'): array` | Localized starter questions from `lang/{locale}/ai_chat.php` |

### Business Rules

- The user travels as an **ID**, not a model, so async-job input survives JSON queue serialization (`User::findOrFail` inside the job).
- Locale falls back to `App::getLocale()` (the `Accept-Language` header), not the stored user locale.
- `answerWithAi` uses `CrmAnalyticsAgent` with `AiSettingsResolver::applyToConfig()`. On failure it throws `ValidationException` with the `ai` key (`messages.ai_generation_failed`).

---

## Design Notes

- Services receive validated data arrays from Form Requests — no raw `Request` objects.
- Services return Eloquent models; controllers wrap them in Resources.
- Services are injected into controllers via constructor injection.
- No repository pattern — Eloquent is accessed directly within services to keep complexity low.
- Report methods return plain arrays (not models), which controllers return as JSON directly.
- When a `CustomerActivity` is completed, `CustomerService` auto-updates the customer's `last_contacted_at` timestamp.
- `health_score` can be manually set or auto-adjusted: completing activities with `positive` outcome increases it, `negative`/`complaint`/`escalated` decreases it.
