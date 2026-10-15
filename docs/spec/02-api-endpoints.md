# 02 — API Endpoints

All endpoints are prefixed with `/api/v1` and require **Sanctum** authentication unless noted otherwise.

Endpoints are further protected by **spatie/laravel-permission** — see [10-roles-permissions.md](10-roles-permissions.md) for role/permission matrix.

---

## Authentication (public)

| Method | URI | Action | Description |
|---|---|---|---|
| `POST` | `/auth/login` | `AuthController@login` | Login and receive token |
| `POST` | `/auth/password/otp/request` | `AuthController@requestPasswordResetOtp` | Send a password reset OTP by email |
| `POST` | `/auth/password/otp/reset` | `AuthController@resetPasswordWithOtp` | Reset password with a single-use OTP |

### Password Reset OTP

Both endpoints are public and rate-limited. No public registration endpoint exists; users are created through authenticated user management.

#### `POST /auth/password/otp/request`

Request: `{ "email": "user@example.com" }`

The response is always successful and uses the same message whether or not an active account exists, preventing email enumeration. For an active account, a six-digit OTP is queued by email. OTPs expire after 10 minutes and a new request replaces the previous code for the same purpose and identifier.

The frontend exposes this same request operation as **Resend code** after the initial request. The UI enforces a 60-second countdown, while the API remains protected by its five-requests-per-minute throttle.

#### `POST /auth/password/otp/reset`

Request:

```json
{
  "email": "user@example.com",
  "otp": "123456",
  "password": "new-password",
  "password_confirmation": "new-password"
}
```

The OTP is single-use, limited to five failed attempts, and must be six digits. A successful reset revokes the user's existing Sanctum tokens. Invalid or expired codes return `422` with an `otp` validation error.

## Quotations

Quotations are created from an opportunity's product lines. Product names, prices, quantities, line discounts, and tax rates are snapshotted per item when the quotation is created; header totals are sums of the lines (see [24-products-catalog.md](24-products-catalog.md)). There is no header-level tax/discount input.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/quotations` | `QuotationController@globalIndex` | Paginated global quotation list (requires `view-quotations`) |
| `GET` | `/customers/{customer}/opportunities/{opportunity}/quotations` | `QuotationController@index` | List opportunity quotations |
| `POST` | `/customers/{customer}/opportunities/{opportunity}/quotations` | `QuotationController@store` | Create a draft quotation |
| `GET` | `/customers/{customer}/opportunities/{opportunity}/quotations/{quotation}` | `QuotationController@show` | Get quotation data |
| `GET` | `/customers/{customer}/opportunities/{opportunity}/quotations/{quotation}/pdf` | `QuotationController@pdf` | Download professional PDF |
| `GET` | `/customers/{customer}/opportunities/{opportunity}/quotations/{quotation}/preview` | `QuotationController@preview` | Stream the PDF inline for browser preview |

PDF endpoints accept an explicit `?lang=en|ar` query param (highest locale priority) because direct browser navigation carries no `Accept-Language` header — see [21-user-settings.md](21-user-settings.md). The frontend `pdfUrl`/`previewUrl` builders append the active UI locale automatically.
| `POST` | `/customers/{customer}/opportunities/{opportunity}/quotations/{quotation}/send` | `QuotationController@send` | Queue PDF email to customer |

Quotation creation supports `valid_until`, `notes`, and `terms`. Omit `terms` to use the locale default (`messages.quotation_default_terms`), which follows the display language; supplied custom text is stored and shown verbatim. Discounts and taxes come from the opportunity lines. Sending requires a customer email and marks the quotation as `sent`.

When quotations are disabled in Company Settings (`features.quotations_enabled = false`), every quotation endpoint — including PDF preview/download, WhatsApp prepare, and public signed downloads — returns `403` with `messages.quotations_disabled`.

### Global List Filters

| Param | Type | Description |
|---|---|---|
| `search` | `string` | Search quotation number, customer name/contact fields, or opportunity title |
| `status` | `string` or `string[]` | Filter by one or more of `draft`, `sent`, `accepted`, `rejected`, `expired`; comma-separated values are also accepted |
| `customer_id` | `int` | Restrict results to one customer |
| `date_from` | `date` | Minimum `valid_until` date, inclusive |
| `date_to` | `date` | Maximum `valid_until` date, inclusive |
| `sort_by` | `string` | `created_at` (default), `quotation_number`, `valid_until`, `total`, or `status` |
| `sort_dir` | `string` | `asc` or `desc` (default: `desc`) |
| `per_page` | `int` | Items per page (default: 15, max: 100) |
| `page` | `int` | Page number |

The response uses Laravel pagination metadata and `QuotationListResource`; see [05-resources.md](05-resources.md).

---

## Invoices

An invoice is created only for a won opportunity and copies line-item snapshots (including discount percent/amount and tax rate) from the latest sent quotation, falling back to the latest quotation or opportunity products.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/invoices` | `InvoiceController@globalIndex` | Paginated global invoice list (requires `view-invoices`) |
| `POST` | `/customers/{customer}/opportunities/{opportunity}/invoice` | `InvoiceController@store` | Create and issue the opportunity's single invoice |
| `GET` | `/customers/{customer}/opportunities/{opportunity}/invoice` | `InvoiceController@show` | Get the opportunity's invoice data |
| `GET` | `/customers/{customer}/opportunities/{opportunity}/invoice/{invoice}/pdf` | `InvoiceController@pdf` | Download professional PDF |
| `GET` | `/customers/{customer}/opportunities/{opportunity}/invoice/{invoice}/preview` | `InvoiceController@preview` | Stream the PDF inline for browser preview |

Invoice PDF endpoints accept the same `?lang=en|ar` override as quotations.

### Global List Filters

| Param | Type | Description |
|---|---|---|
| `search` | `string` | Search invoice number, customer name/contact fields, or opportunity title |
| `status` | `string` or `string[]` | Filter by one or more of `issued`, `paid`, `cancelled`; comma-separated values are also accepted |
| `customer_id` | `int` | Restrict results to one customer |
| `date_from` | `date` | Minimum `issued_at` date, inclusive |
| `date_to` | `date` | Maximum `issued_at` date, inclusive |
| `sort_by` | `string` | `created_at` (default), `invoice_number`, `issued_at`, `total`, or `status` |
| `sort_dir` | `string` | `asc` or `desc` (default: `desc`) |
| `per_page` | `int` | Items per page (default: 15, max: 100) |
| `page` | `int` | Page number |

The response uses Laravel pagination metadata and `InvoiceListResource`; see [05-resources.md](05-resources.md).

When invoices are disabled in Company Settings (`features.invoices_enabled = false`), every invoice endpoint returns `403` with `messages.invoices_disabled`.

---

## Taxes

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/taxes` | `TaxController@index` | List taxes, ordered by name (`?active_only=1` for pickers; requires `view-taxes`) |
| `POST` | `/taxes` | `TaxController@store` | Create a tax (`name`, `percent` 0–100, `is_active`) |
| `GET` | `/taxes/{tax}` | `TaxController@show` | Show tax with `products_count` |
| `PUT` | `/taxes/{tax}` | `TaxController@update` | Update a tax (all fields `sometimes`) |
| `DELETE` | `/taxes/{tax}` | `TaxController@destroy` | Delete (422 while products reference it) |

---

## WhatsApp Messaging (outbound)

See [32-whatsapp-messaging.md](32-whatsapp-messaging.md) for full behavior and configuration. Prepare and send routes require Sanctum authentication and relevant customer, opportunity, quotation, or invoice policy authorization. Preparing requires `view-customers`; sending also requires permission to create customer activities. There is no separate WhatsApp permission.

| Method | URI | Action | Description |
|---|---|---|---|
| `POST` | `/customers/{customer}/whatsapp/prepare` | `WhatsAppController@prepareCustomer` | Prepare an editable general customer message |
| `POST` | `/customers/{customer}/opportunities/{opportunity}/quotations/{quotation}/whatsapp/prepare` | `WhatsAppController@prepareQuotation` | Prepare a quotation message with its signed PDF link |
| `POST` | `/customers/{customer}/opportunities/{opportunity}/invoice/{invoice}/whatsapp/prepare` | `WhatsAppController@prepareInvoice` | Prepare an invoice message with its signed PDF link |
| `POST` | `/customers/{customer}/whatsapp/send` | `WhatsAppController@sendCustomer` | Open Click-to-Chat or send through the configured Cloud API; log a customer activity |
| `POST` | `/customers/{customer}/opportunities/{opportunity}/whatsapp/send` | `WhatsAppController@sendOpportunity` | Send/log a message against the customer and opportunity |

Prepare accepts an optional `custom_message` string (maximum 2,000 characters). Send accepts `phone` (6–30 characters), `message` (1–4,000 characters), optional `subject` (maximum 255 characters), and optional boolean `via_cloud_api`. Phone digits are normalized, and numbers shorter than six digits after normalization are rejected.

The following PDF download routes do not require authentication, but require a valid, unexpired Laravel signature. Generated links expire after seven days.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/public/quotations/{quotation}/download` | `PublicDocumentController@downloadQuotation` | Download a quotation PDF using a temporary signed URL |
| `GET` | `/public/invoices/{invoice}/download` | `PublicDocumentController@downloadInvoice` | Download an invoice PDF using a temporary signed URL |

Click-to-Chat opens `wa.me`; the CRM can log initiation but cannot verify that the user sent or delivered the message. Cloud API use is optional; neither path currently receives inbound messages or delivery/read status webhooks.

---

## Authentication (protected)


| Method | URI | Action | Description |
|---|---|---|---|
| `POST` | `/auth/logout` | `AuthController@logout` | Revoke current token |
| `GET` | `/auth/me` | `AuthController@me` | Get authenticated user profile |

---

## Customers (unified — `type` individual|company, `is_lead` flag)

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/customers` | `CustomerController@index` | Paginated list. Filters: `search`, `type`, `is_lead`, `lead_status`, `status`, `source`, `assigned_to`, `account_manager_id`, `contact`, `city`/`address`, `score_min/max`, `tag` |
| `POST` | `/customers` | `CustomerController@store` | Create customer/lead (`type` required; company needs `company_name`, individual needs `first/last_name`; nested `contacts[]`, `addresses[]`) |
| `GET` | `/customers/{customer}` | `CustomerController@show` | Show with assignee, account manager, opportunities |
| `PUT` | `/customers/{customer}` | `CustomerController@update` | Update customer |
| `DELETE` | `/customers/{customer}` | `CustomerController@destroy` | Soft-delete |
| `PATCH` | `/customers/{customer}/lead-status` | `CustomerController@updateLeadStatus` | Transition `lead_status` (validated transitions, only when `is_lead`) |
| `PATCH` | `/customers/{customer}/assign` | `CustomerController@assign` | Assign to a user |
| `POST` | `/customers/{customer}/convert` | `CustomerController@convert` | Manually convert lead-customer (`is_lead=false`) |
| `GET` | `/customers/{customer}/score` | `CustomerController@score` | Get score + breakdown |
| `POST` | `/customers/{customer}/score/recalculate` | `CustomerController@recalculateScore` | Force recalculate |

### Query Parameters (index)

| Param | Type | Description |
|---|---|---|
| `search` | `string` | Search name, email, phone, plus JSON `contacts`/`addresses` |
| `type` | `string` | `individual` \| `company` |
| `is_lead` | `bool` | `true` = Leads list, `false` = Customers list |
| `lead_status` | `string` | Filter lead pipeline status |
| `status` | `string` | Filter customer status (`active`, `at_risk`, `churned`, `paused`, `renewed`) |
| `account_manager_id` | `int` | Filter by account manager |
| `assigned_to` | `int` | Filter by assignee |
| `health_below` | `int` | Filter customers with health_score below threshold |
| `follow_up_due` | `bool` | If true, only customers where `next_follow_up_at <= now()` |
| `contact` | `string` | Search contacts JSON (email/phone) |
| `city` / `address` | `string` | Search addresses JSON |
| `sort_by` | `string` | Column to sort by (default: `created_at`) |
| `sort_dir` | `string` | `asc` or `desc` (default: `desc`) |
| `per_page` | `int` | Items per page (default: 15, max: 100) |

---

## Opportunities (nested under Customer)

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/customers/{customer}/opportunities` | `OpportunityController@index` | List opportunities for a customer |
| `POST` | `/customers/{customer}/opportunities` | `OpportunityController@store` | Create opportunity |
| `GET` | `/customers/{customer}/opportunities/{opportunity}` | `OpportunityController@show` | Show opportunity |
| `PUT` | `/customers/{customer}/opportunities/{opportunity}` | `OpportunityController@update` | Update opportunity |
| `DELETE` | `/customers/{customer}/opportunities/{opportunity}` | `OpportunityController@destroy` | Remove opportunity |
| `PATCH` | `/customers/{customer}/opportunities/{opportunity}/stage` | `OpportunityController@updateStage` | Transition stage (validated; `won` auto-converts lead-customer) |
| `GET` | `/customers/{customer}/opportunities/{opportunity}/activities` | `OpportunityActivityController@index` | List opportunity activities (filters: type, channel, status, dates, reminder, owner, search) |
| `POST` | `/customers/{customer}/opportunities/{opportunity}/activities` | `OpportunityActivityController@store` | Log activity (type, channel, subject, due date, reminder) |
| `PUT` | `/customers/{customer}/opportunities/{opportunity}/activities/{activity}` | `OpportunityActivityController@update` | Update activity (rescheduling resets the pending reminder) |
| `DELETE` | `/customers/{customer}/opportunities/{opportunity}/activities/{activity}` | `OpportunityActivityController@destroy` | Remove activity |
| `PATCH` | `/customers/{customer}/opportunities/{opportunity}/activities/{activity}/complete` | `OpportunityActivityController@complete` | Mark completed with optional outcome |
| `GET` | `/customers/{customer}/opportunities/{opportunity}/products` | `OpportunityProductController@index` | List attached products (with pivot quantity/notes) |
| `POST` | `/customers/{customer}/opportunities/{opportunity}/products` | `OpportunityProductController@store` | Attach product |
| `PUT` | `/customers/{customer}/opportunities/{opportunity}/products/{product}` | `OpportunityProductController@update` | Update pivot quantity/notes |
| `DELETE` | `/customers/{customer}/opportunities/{opportunity}/products/{product}` | `OpportunityProductController@destroy` | Detach product |
| `POST` | `/customers/{customer}/opportunities/{opportunity}/products/sync` | `OpportunityProductController@sync` | Replace all attached products |

---

## Dashboard

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/dashboard/lead-stats` | `DashboardController@leadStats` | Aggregate lead stats |

---

## Reports (requires `view-reports` permission)

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/reports/user-performance` | `ReportController@userPerformance` | Sales rep performance metrics |
| `GET` | `/reports/company` | `ReportController@company` | Organization-level analytics |
| `GET` | `/reports/customer` | `ReportController@customer` | Won-lead customer analytics |
| `POST` | `/reports/user-performance/async` | `ReportController@userPerformanceAsync` | Queue user-performance report |
| `POST` | `/reports/company/async` | `ReportController@companyAsync` | Queue company report |
| `POST` | `/reports/customer/async` | `ReportController@customerAsync` | Queue customer report |
| `POST` | `/reports/user-performance/export/async` | `ReportController@exportUserPerformanceAsync` | Queue xlsx/pdf export |
| `POST` | `/reports/company/export/async` | `ReportController@exportCompanyAsync` | Queue xlsx/pdf export |
| `POST` | `/reports/customer/export/async` | `ReportController@exportCustomerAsync` | Queue xlsx/pdf export |

See [11-reports.md](11-reports.md) for full response shapes and query parameters.

---

## Async Jobs (polling)

Generic polling endpoints for any background job. Scoped to the authenticated user’s own jobs.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/async-jobs` | `AsyncJobController@index` | List own jobs (filterable by `type`, `status`) |
| `GET` | `/async-jobs/{asyncJob}` | `AsyncJobController@show` | Poll a single job status |
| `GET` | `/async-jobs/{asyncJob}/download` | `AsyncJobController@download` | Download completed export file |

---

## AI Chat

Stateless reporting & analytics chat over live CRM aggregates. See [34-ai-chat.md](34-ai-chat.md) for the snapshot, agent rules, and frontend behavior.

| Method | URI | Action | Description |
|---|---|---|---|
| `POST` | `/ai-chat/async` | `AiChatController@chatAsync` | Ask a question (requires `create-ai-chats`, throttled 10/min) — returns pending `AsyncJob`, poll for `{ answer, sources, locale }` |
| `GET` | `/ai-chat/suggested-questions` | `AiChatController@suggestedQuestions` | Localized starter questions (requires `create-ai-chats`) |

---

## Customers (post-sale lifecycle) — see unified Customers section above

Customer activities, opportunities, tags, notes, timeline, and attachments are nested under `/customers/{customer}`. There is no separate lead/customer creation flow: conversion happens via `POST /customers/{customer}/convert` or automatically when an opportunity is marked `won`.

---

## Customer Activities (nested under Customer)

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/activities` | `CustomerActivityController@globalIndex` | List activities across viewable customers (filters: type, channel, status, dates, reminder, owner, customer, search) |
| `GET` | `/customers/{customer}/activities` | `CustomerActivityController@index` | List activities for a customer |
| `POST` | `/customers/{customer}/activities` | `CustomerActivityController@store` | Log a post-sale activity |
| `PUT` | `/customers/{customer}/activities/{activity}` | `CustomerActivityController@update` | Update activity |
| `DELETE` | `/customers/{customer}/activities/{activity}` | `CustomerActivityController@destroy` | Remove activity |
| `PATCH` | `/customers/{customer}/activities/{activity}/complete` | `CustomerActivityController@complete` | Mark completed with optional outcome |

---

### Response Shape (`lead-stats`)

```json
{
  "data": {
    "total_leads": 142,
    "by_status": {
      "new": 30,
      "contacted": 25,
      "qualified": 20,
      "proposal": 15,
      "negotiation": 12,
      "won": 25,
      "lost": 15
    },
    "total_estimated_value": "1250000.00",
    "won_value": "450000.00",
    "conversion_rate": "17.61"
  }
}
```

---

## Notifications

See [12-notifications.md](12-notifications.md) for full spec.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/notifications` | `NotificationController@index` | List my notifications |
| `GET` | `/notifications/unread-count` | `NotificationController@unreadCount` | Count of unread |
| `PATCH` | `/notifications/{id}/read` | `NotificationController@markRead` | Mark as read |
| `PATCH` | `/notifications/read-all` | `NotificationController@markAllRead` | Mark all as read |
| `DELETE` | `/notifications/{id}` | `NotificationController@destroy` | Delete notification |

---

## Tasks

See [13-tasks.md](13-tasks.md) for full spec.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/tasks` | `TaskController@index` | List tasks |
| `POST` | `/tasks` | `TaskController@store` | Create task |
| `GET` | `/tasks/{task}` | `TaskController@show` | Show task |
| `PUT` | `/tasks/{task}` | `TaskController@update` | Update task |
| `DELETE` | `/tasks/{task}` | `TaskController@destroy` | Delete task |
| `PATCH` | `/tasks/{task}/complete` | `TaskController@complete` | Mark completed |
| `GET` | `/tasks/my-summary` | `TaskController@mySummary` | My task counts |

---

## Tags

See [14-tags.md](14-tags.md) for full spec.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/tags` | `TagController@index` | List all tags |
| `POST` | `/tags` | `TagController@store` | Create tag |
| `PUT` | `/tags/{tag}` | `TagController@update` | Update tag |
| `DELETE` | `/tags/{tag}` | `TagController@destroy` | Delete tag |
| `
| `POST` | `/customers/{customer}/tags` | `CustomerController@syncTags` | Sync tags on customer |
| `

---

## Email Templates

See [15-email-campaigns.md](15-email-campaigns.md) for full spec.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/email-templates` | `EmailTemplateController@index` | List templates |
| `POST` | `/email-templates` | `EmailTemplateController@store` | Create template |
| `GET` | `/email-templates/{template}` | `EmailTemplateController@show` | Show template |
| `PUT` | `/email-templates/{template}` | `EmailTemplateController@update` | Update template |
| `DELETE` | `/email-templates/{template}` | `EmailTemplateController@destroy` | Delete template |
| `POST` | `/email-templates/{template}/preview` | `EmailTemplateController@preview` | Preview with sample data |
| `POST` | `/email-templates/generate` | `EmailTemplateController@generate` | AI-generate a draft template |

---

## Campaigns

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/campaigns` | `CampaignController@index` | List campaigns |
| `POST` | `/campaigns` | `CampaignController@store` | Create campaign (draft) |
| `GET` | `/campaigns/{campaign}` | `CampaignController@show` | Show with stats |
| `PUT` | `/campaigns/{campaign}` | `CampaignController@update` | Update (draft only) |
| `DELETE` | `/campaigns/{campaign}` | `CampaignController@destroy` | Delete (draft only) |
| `POST` | `/campaigns/{campaign}/send` | `CampaignController@send` | Send now |
| `POST` | `/campaigns/{campaign}/schedule` | `CampaignController@schedule` | Schedule for later |
| `POST` | `/campaigns/{campaign}/cancel` | `CampaignController@cancel` | Cancel |
| `GET` | `/campaigns/{campaign}/recipients` | `CampaignController@recipients` | Recipient list |

---

## Audit Logs

See [16-audit-log.md](16-audit-log.md) for full spec.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/audit-logs` | `AuditLogController@index` | Global log (admin only) |
| `GET` | `/audit-logs/entity/{type}/{id}` | `AuditLogController@forEntity` | History for one entity |

---

## File Attachments

See [17-file-attachments.md](17-file-attachments.md) for full spec.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/{entity}/{id}/attachments` | `AttachmentController@index` | List entity's attachments |
| `POST` | `/{entity}/{id}/attachments` | `AttachmentController@store` | Upload file(s) |
| `GET` | `/attachments/{attachment}` | `AttachmentController@show` | Attachment metadata |
| `GET` | `/attachments/{attachment}/download` | `AttachmentController@download` | Download file |
| `DELETE` | `/attachments/{attachment}` | `AttachmentController@destroy` | Delete attachment |

> `{entity}` = `customers`, `opportunities`

---

## Import / Export

See [18-import-export.md](18-import-export.md) for full spec.

| Method | URI | Action | Description |
|---|---|---|---|
| `POST` | `/import/{entity_type}` | `ImportController@store` | Upload CSV and start import |
| `GET` | `/import/{import}` | `ImportController@show` | Check import status |
| `GET` | `/import/{entity_type}/template` | `ImportController@template` | Download blank CSV template |
| `GET` | `/import` | `ImportController@index` | List my imports |
| `GET` | `/export/leads` | `ExportController@leads` | Export lead-customers CSV (`is_lead=1`) |
| `GET` | `/export/opportunities` | `ExportController@opportunities` | Export opportunities CSV |


| `GET` | `/export/customers` | `ExportController@customers` | Export customers CSV |
| `GET` | `/export/products` | `ExportController@products` | Export products CSV |
| `GET` | `/export/categories` | `ExportController@categories` | Export product categories CSV |

Import entity types: `leads`, `customers`, `opportunities`, `products`, and `categories`. Product/category relationships use CSV slugs (`category_slug`, `parent_slug`). Exports are streamed CSV; product exports include inactive products unless `is_active` is specified, and category exports emit parents before children.

---

## Global Search

See [19-global-search.md](19-global-search.md) for full spec.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/search` | `SearchController@index` | Search across all entity types |

---

## Enhanced Dashboard

See [20-enhanced-dashboard.md](20-enhanced-dashboard.md) for full spec.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/dashboard` | `DashboardController@index` | Full dashboard data |
| `GET` | `/dashboard/activity-feed` | `DashboardController@activityFeed` | Recent activity feed |

---

## User Settings

See [21-user-settings.md](21-user-settings.md) for full spec.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/settings` | `UserSettingsController@show` | My settings |
| `PUT` | `/settings` | `UserSettingsController@update` | Update settings |
| `POST` | `/settings/avatar` | `UserSettingsController@uploadAvatar` | Upload avatar |
| `DELETE` | `/settings/avatar` | `UserSettingsController@deleteAvatar` | Remove avatar |
| `PUT` | `/settings/password` | `UserSettingsController@changePassword` | Change password |

## Role Management (admin)

Allows creating custom roles with a chosen set of permissions. The three system roles (`admin`, `manager`, `sales_rep`) cannot be deleted. Requires `manage-users` permission.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/permissions` | `RoleController@permissions` | List all available permission names |
| `GET` | `/roles` | `RoleController@index` | List all roles with their permissions |
| `POST` | `/roles` | `RoleController@store` | Create a custom role |
| `GET` | `/roles/{role}` | `RoleController@show` | Show role detail with user count |
| `PUT` | `/roles/{role}` | `RoleController@update` | Rename or update role permissions |
| `DELETE` | `/roles/{role}` | `RoleController@destroy` | Delete a custom role (system roles protected) |

### `POST /roles` — Request body

| Field | Type | Required | Description |
|---|---|---|---|
| `name` | `string` | ✅ | Unique role name, max 100 chars |
| `permissions` | `string[]` | ❌ | Array of permission names to assign |

### `RoleResource` response shape

```json
{
  "id": 4,
  "name": "support_agent",
  "is_system": false,
  "permissions": ["view-customers", "view-opportunities"],
  "users_count": 3,
  "created_at": "2026-04-04T10:00:00Z"
}
```

---

## User Management (admin)

The `role` field in create/update user requests accepts any role name — including custom roles created via the Role Management endpoints above.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/users` | `UserController@index` | List users |
| `GET` | `/users/assignable` | `UserController@assignable` | Search active users for assignment pickers (`search`, `per_page` ≤ 50). Requires `view-assignable-users` |
| `GET` | `/users/assignable/{user}` | `UserController@showAssignable` | Single active user for assignment pickers (404 when inactive). Requires `view-assignable-users` |
| `POST` | `/users` | `UserController@store` | Create user with role |
| `GET` | `/users/{user}` | `UserController@show` | Show user |
| `PUT` | `/users/{user}` | `UserController@update` | Update user |
| `DELETE` | `/users/{user}` | `UserController@destroy` | Deactivate user |
| `PUT` | `/users/{user}/role` | `UserController@updateRole` | Change role |
| `POST` | `/users/{user}/activate` | `UserController@activate` | Reactivate user |

---

## Custom Fields

See [22-custom-fields.md](22-custom-fields.md) for full spec.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/custom-fields/entities` | `CustomFieldGroupController@entities` | List registered models and category support |
| `GET` | `/custom-fields/{entityType}` | `CustomFieldGroupController@showEntity` | Return `{ group, definitions }` for model scope |
| `GET` | `/custom-fields/{entityType}/categories/{category}` | `CustomFieldGroupController@showCategory` | Return the category's independent group and definitions |
| `POST` | `/custom-fields/groups` | `CustomFieldGroupController@store` | Create a model- or category-scoped group |
| `PUT` | `/custom-fields/groups/{group}` | `CustomFieldGroupController@update` | Update group name/description |
| `DELETE` | `/custom-fields/groups/{group}` | `CustomFieldGroupController@destroy` | Delete group and strip its values from rows |
| `POST` | `/custom-fields/{entityType}` | `CustomFieldDefinitionController@store` | Create model-scoped definition |
| `POST` | `/custom-fields/{entityType}/categories/{category}/definitions` | `CustomFieldDefinitionController@storeCategory` | Create product-category definition |
| `GET` | `/custom-fields/definitions/{definition}` | `CustomFieldDefinitionController@show` | Show definition |
| `PUT` | `/custom-fields/definitions/{definition}` | `CustomFieldDefinitionController@update` | Update definition |
| `DELETE` | `/custom-fields/definitions/{definition}` | `CustomFieldDefinitionController@destroy` | Delete definition + clean data |

`{entityType}` is constrained to keys in the custom-field entity registry.

---

## Lead Scoring

See [23-lead-scoring.md](23-lead-scoring.md) for full spec.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/customers/{customer}/score` | `CustomerController@score` | Get score + breakdown |
| `POST` | `/customers/{customer}/score/recalculate` | `CustomerController@recalculateScore` | Force recalculate |

---

## Products / Services Catalog

See [24-products-catalog.md](24-products-catalog.md) for full spec.

### Product Categories

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/product-categories` | `ProductCategoryController@index` | Flat list of all categories |
| `GET` | `/product-categories/tree` | `ProductCategoryController@tree` | Nested tree |
| `POST` | `/product-categories` | `ProductCategoryController@store` | Create category |
| `GET` | `/product-categories/{category}` | `ProductCategoryController@show` | Show category + children |
| `PUT` | `/product-categories/{category}` | `ProductCategoryController@update` | Update category |
| `DELETE` | `/product-categories/{category}` | `ProductCategoryController@destroy` | Delete (blocked if products exist) |
| `POST` | `/product-categories/reorder` | `ProductCategoryController@reorder` | Bulk update sort_order |

### Products & Opportunity Products
| `GET` | `/products` | `ProductController@index` | List products (filter by category, search) |
| `POST` | `/products` | `ProductController@store` | Create product |
| `GET` | `/products/{product}` | `ProductController@show` | Show product |
| `GET` | `/products/{product}/opportunities` | `ProductController@opportunities` | Opportunities containing this product, including opportunity-product pivot values |
| `PUT` | `/products/{product}` | `ProductController@update` | Update product |
| `DELETE` | `/products/{product}` | `ProductController@destroy` | Deactivate product |
| `GET` | `/product-categories/{category}/products` | `ProductController@byCategory` | Products in a category (includes descendants) |

---

## Webhooks

See [25-webhooks.md](25-webhooks.md) for full spec.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/webhooks` | `WebhookEndpointController@index` | List webhook endpoints |
| `POST` | `/webhooks` | `WebhookEndpointController@store` | Create endpoint (returns secret) |
| `GET` | `/webhooks/{webhook}` | `WebhookEndpointController@show` | Show endpoint details |
| `PUT` | `/webhooks/{webhook}` | `WebhookEndpointController@update` | Update endpoint |
| `DELETE` | `/webhooks/{webhook}` | `WebhookEndpointController@destroy` | Delete endpoint |
| `POST` | `/webhooks/{webhook}/rotate-secret` | `WebhookEndpointController@rotateSecret` | Generate new secret |
| `GET` | `/webhooks/{webhook}/deliveries` | `WebhookEndpointController@deliveries` | List delivery attempts |
| `POST` | `/webhook-deliveries/{delivery}/redeliver` | `WebhookEndpointController@redeliver` | Retry a delivery |

---

---

## Notes / Comments

See [27-notes-comments.md](27-notes-comments.md) for full spec.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/{entity}/{id}/notes` | `NoteController@index` | List entity's notes (threaded) |
| `POST` | `/{entity}/{id}/notes` | `NoteController@store` | Add note to entity |
| `PUT` | `/notes/{note}` | `NoteController@update` | Edit note (author only) |
| `DELETE` | `/notes/{note}` | `NoteController@destroy` | Soft-delete note |
| `POST` | `/notes/{note}/toggle-pin` | `NoteController@togglePin` | Pin/unpin note |

> `{entity}` = `customers`, `opportunities`

---

## Activity Timeline

See [28-activity-timeline.md](28-activity-timeline.md) for full spec.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/{entity}/{id}/timeline` | `TimelineController@index` | Unified chronological timeline |

> `{entity}` = `customers`, `opportunities`

---

## Standard Response Envelope

### Success (single resource)

```json
{
  "data": { ... }
}
```

### Success (collection)

```json
{
  "data": [ ... ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "last_page": 10, "per_page": 15, "total": 142 }
}
```

### Validation Error (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field": ["Error message"]
  }
}
```

### Not Found (404)

```json
{
  "message": "Resource not found."
}
```
