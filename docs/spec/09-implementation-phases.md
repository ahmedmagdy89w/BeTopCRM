# 09 — Implementation Phases

Each phase is self-contained and can be tested independently before moving to the next.

---

## Phase 1: Migrations & Models

**Goal:** Create the database schema and Eloquent models.

- [x] Create `organizations` migration
- [x] Create `leads` migration
- [x] Create `lead_activities` migration
- [x] Create `contacts` migration
- [x] Create `customers` migration
- [x] Create `customer_activities` migration
- [x] Create `deals` migration
- [x] Create `Organization` model with relationships, fillable, casts, scopes
- [x] Create `Lead` model with relationships, fillable, casts, scopes, status constants
- [x] Create `LeadActivity` model with relationships, fillable, casts
- [x] Create `Contact` model with relationships, fillable, casts
- [x] Create `Customer` model with relationships, fillable, casts, scopes, accessors
- [x] Create `CustomerActivity` model with relationships, fillable, casts, constants, helpers
- [x] Create `Deal` model with relationships, fillable, casts, constants
- [x] Add CRM relationships to `User` model
- [x] Run `php artisan migrate` to verify

---

## Phase 2: Form Requests

**Goal:** Define validation rules for all endpoints.

- [x] `StoreOrganizationRequest` / `UpdateOrganizationRequest`
- [x] `StoreContactRequest` / `UpdateContactRequest`
- [x] `StoreLeadRequest` / `UpdateLeadRequest`
- [x] `UpdateLeadStatusRequest` (with transition validation)
- [x] `AssignLeadRequest`
- [x] `StoreLeadActivityRequest` / `UpdateLeadActivityRequest`
- [x] `UpdateCustomerRequest`
- [x] `StoreCustomerActivityRequest` / `UpdateCustomerActivityRequest` / `CompleteCustomerActivityRequest`
- [x] `StoreDealRequest` / `UpdateDealRequest`

---

## Phase 3: API Resources

**Goal:** Define response shapes.

- [x] `OrganizationResource` / `OrganizationCollection`
- [x] `LeadResource` / `LeadCollection`
- [x] `LeadActivityResource`
- [x] `ContactResource`
- [x] `CustomerResource` / `CustomerCollection`
- [x] `CustomerActivityResource`
- [x] `DealResource`

---

## Phase 4: Services

**Goal:** Implement business logic.

- [x] `OrganizationService` (list, create, update, delete)
- [x] `LeadService` (list, create, update, delete, updateStatus, assign)
- [x] `CustomerService` (list, createFromLead, update, delete)
- [x] `CustomerActivityService` (list, create, update, delete, complete)
- [x] `DealService` (list, create, show, update, delete)

---

## Phase 5: Filters

**Goal:** Pipeline-based query filters.

- [x] `OrganizationFilter` (search, industry)
- [x] `LeadFilter` (search, status, source, assigned_to, organization_id)
- [x] `CustomerFilter` (search, status, account_manager_id, organization_id, health_below, follow_up_due)
- [x] `CustomerActivityFilter` (type)

---

## Phase 6: Controllers

**Goal:** Wire HTTP layer.

- [x] `OrganizationController` (index, store, show, update, destroy)
- [x] `ContactController` (index, store, update, destroy)
- [x] `LeadController` (index, store, show, update, destroy, updateStatus, assign)
- [x] `LeadActivityController` (index, store, update, destroy, complete)
- [x] `DashboardController` (leadStats)
- [x] `CustomerController` (index, show, update, destroy)
- [x] `CustomerActivityController` (index, store, update, destroy, complete)
- [x] `DealController` (index, store, show, update, destroy)

---

## Phase 7: Routes

**Goal:** Register all endpoints in `routes/api.php`.

- [x] Organization CRUD routes
- [x] Nested contact routes under organizations
- [x] Lead CRUD routes + status/assign actions
- [x] Nested activity routes under leads
- [x] Dashboard route
- [x] Customer CRUD routes
- [x] Nested customer activity routes under customers
- [x] Nested deal routes under customers

---

## Phase 8: Events & Listeners

**Goal:** Domain events for status changes.

- [x] `LeadStatusChanged` event
- [x] `LogLeadStatusChange` listener
- [x] Register in `AppServiceProvider`

---

## Phase 9: Factories & Seeders

**Goal:** Generate test data.

- [x] `OrganizationFactory`
- [x] `LeadFactory`
- [x] `LeadActivityFactory`
- [x] `ContactFactory`
- [x] `CustomerFactory`
- [x] `CustomerActivityFactory`
- [x] `DealFactory`
- [ ] `OrganizationSeeder`
- [ ] `LeadSeeder`
- [ ] Update `DatabaseSeeder`

---

## Phase 10: Roles & Permissions (spatie/laravel-permission)

**Goal:** Install package, define roles/permissions, protect endpoints.

- [x] `composer require spatie/laravel-permission`
- [x] Publish & run spatie migrations
- [x] Create `RolesAndPermissionsSeeder` with roles: `admin`, `manager`, `sales_rep`
- [x] Define permissions: `manage-organizations`, `manage-leads`, `manage-contacts`, `assign-leads`, `manage-customers`, `view-customers`, `manage-deals`, `view-reports`, `manage-users`
- [x] Add `HasRoles` trait to `User` model
- [x] Create Policies: `OrganizationPolicy`, `LeadPolicy`, `ContactPolicy`, `LeadActivityPolicy`, `CustomerPolicy`, `CustomerActivityPolicy`, `DealPolicy`
- [x] Apply `permission` middleware to routes
- [x] Update Form Requests `authorize()` methods to use policies

---

## Phase 11: Reports

**Goal:** Reporting endpoints for user performance, company, and customer analytics.

- [x] `ReportService` (userPerformance, companyReport, customerReport)
- [x] `ReportController` (3 endpoints)
- [x] Register report routes (protected by `view-reports` permission)

---

## Phase 12: Notifications & Reminders

**Goal:** In-app notification system with scheduled reminders.

- [x] Run `php artisan notifications:table` and migrate
- [x] Create 6 notification classes (`LeadAssigned`, `LeadStatusChanged`, `FollowUpReminder`, `DealExpiring`, `CustomerHealthAlert`, `TaskDue`)
- [x] `NotificationController` (index, unreadCount, markRead, markAllRead, destroy)
- [x] `NotificationResource`
- [x] Register notification routes
- [x] Dispatch notifications from `LeadService::assign()`, `LeadStatusChanged` listener, `CustomerActivityService::complete()`
- [x] Create scheduled commands: `SendFollowUpReminders`, `SendDealExpiryAlerts`, `SendTaskReminders`
- [x] Register commands in `routes/console.php`

---

## Phase 13: Tasks

**Goal:** General-purpose task/to-do system with polymorphic entity linking.

- [x] Create `tasks` migration
- [x] Create `Task` model with polymorphic `taskable`, scopes, helpers
- [x] `StoreTaskRequest` / `UpdateTaskRequest`
- [x] `TaskResource`
- [x] `TaskService` (list, create, update, delete, complete, mySummary)
- [x] `TaskFilter` (status, priority, user_id, entity_type, overdue, due_today)
- [x] `TaskController` (index, store, show, update, destroy, complete, mySummary)
- [x] `TaskPolicy`
- [x] Register task routes
- [x] Add `tasks()` and `createdTasks()` to User model
- [x] `TaskTest`

---

## Phase 14: Tags

**Goal:** Polymorphic tagging for leads, customers, organizations.

- [x] Create `tags` and `taggables` migrations
- [x] Create `Tag` model
- [x] Create `HasTags` trait, apply to Lead, Customer, Organization
- [x] `StoreTagRequest` / `UpdateTagRequest` / `SyncTagsRequest`
- [x] `TagResource`
- [x] `TagService` (list, create, update, delete)
- [x] `TagController` (index, store, update, destroy)
- [x] Add `syncTags` action to Lead/Customer/OrganizationController
- [x] Add `tag` query param to Lead/Customer/Organization filters
- [x] `TagPolicy`
- [x] Register tag routes
- [x] `TagTest`

---

## Phase 15: Email Templates & Campaigns

**Goal:** Reusable email templates and bulk campaign sending.

- [x] Create `email_templates`, `campaigns`, `campaign_recipients` migrations
- [x] Create `EmailTemplate`, `Campaign`, `CampaignRecipient` models
- [x] Validation: `StoreEmailTemplateRequest`, `StoreCampaignRequest`, `ScheduleCampaignRequest`
- [x] `EmailTemplateResource`, `CampaignResource`, `CampaignRecipientResource`
- [x] `CampaignService` (list, create, update, delete, send, schedule, cancel)
- [x] `SendCampaignEmailJob` (queued mail job)
- [x] `EmailTemplateController` (CRUD + preview)
- [x] `CampaignController` (CRUD + send, schedule, cancel, recipients)
- [x] `CampaignPolicy`
- [ ] Create `SendScheduledCampaigns` command, register in scheduler
- [x] Register routes
- [x] `EmailTemplateTest`, `CampaignTest`

---

## Phase 16: Audit Log

**Goal:** Automatic change tracking across all CRM entities.

- [x] Create `audit_logs` migration
- [x] Create `AuditLog` model with `record()` factory method
- [x] Create `Auditable` trait, apply to all relevant models
- [x] `AuditLogResource`
- [x] `AuditLogService` (list, forEntity)
- [x] `AuditLogController` (index, forEntity)
- [x] Register routes (admin-only access)
- [x] Create `PruneAuditLogs` command, register in scheduler
- [x] Create `config/crm.php` with audit retention setting
- [x] `AuditLogTest`

---

---

## Phase 17: File Attachments

**Goal:** Polymorphic file upload/download system.

- [x] Create `attachments` migration
- [x] Create `Attachment` model with `attachable` morphTo
- [x] Create `HasAttachments` trait, apply to Lead, Customer, Organization, Deal
- [x] `StoreAttachmentRequest` (file validation, max size)
- [x] `AttachmentResource`
- [x] `AttachmentService` (list, store, download, delete)
- [x] `AttachmentController` (index, store, show, download, destroy)
- [x] `AttachmentPolicy` (delegates to parent entity policy)
- [ ] Add `attachments` disk to `config/filesystems.php`
- [x] Register attachment routes
- [x] `AttachmentTest`

---

## Phase 18: Import / Export

**Goal:** CSV import with queued processing and streaming CSV export.

- [x] Create `imports` migration
- [x] Create `Import` model with status tracking
- [x] `StoreImportRequest` (file validation, entity_type enum)
- [x] `ImportResource`
- [x] `ProcessImportJob` (queued row-by-row import with error tracking)
- [x] `ImportService` (create, status, template download)
- [x] `ExportService` (stream CSV for each entity type)
- [x] `ImportController` (store, show, template, index)
- [x] `ExportController` (leads, customers, opportunities, deals, products, categories)
- [x] Register import/export routes
- [x] Customer, product, and category CSV templates and imports; duplicate and row-level error reporting
- [x] `ImportExportTest` (customer classification, catalog relationships, malformed rows, and streamed exports)

---

## Phase 19: Global Search

**Goal:** Single endpoint to search across all entity types.

- [x] `SearchService` (search leads, customers, organizations, contacts — permission-scoped)
- [x] `SearchResultResource` (grouped by entity type)
- [x] `SearchController` (index)
- [x] Register search route
- [x] `SearchTest`

---

## Phase 20: Enhanced Dashboard

**Goal:** Comprehensive dashboard with pipeline, customer, task, revenue, and activity data.

- [x] `DashboardService` (aggregate data across all entities, role-scoped)
- [x] Update `DashboardController` (index with full sections, activityFeed)
- [x] Register dashboard routes
- [x] `DashboardTest` (test pipeline, customers, tasks, revenue, activity feed, role scoping)

---

## Phase 21: User Settings & Management

**Goal:** User settings, avatar upload, password change, and admin user CRUD.

- [x] Create `add_settings_columns_to_users_table` migration
- [x] `UpdateSettingsRequest`, `ChangePasswordRequest`, `UploadAvatarRequest`
- [x] `UserSettingsService` (update settings, upload avatar, change password)
- [x] `UserSettingsController` (show, update, uploadAvatar, deleteAvatar, changePassword)
- [x] `StoreUserRequest`, `UpdateUserRequest`, `UpdateUserRoleRequest`
- [x] `UserService` (list, create, update, deactivate, activate, updateRole)
- [x] `UserResource`
- [x] `UserController` (index, store, show, update, destroy, updateRole, activate)
- [x] `UserPolicy` (manage-users permission)
- [x] Add `is_active` check to auth flow
- [x] Register user settings and user management routes
- [x] `UserSettingsTest`, `UserManagementTest`

---

## Phase 22: Custom Fields

**Goal:** Admin-defined custom fields per entity type with JSON storage.

- [x] Create `custom_field_definitions` migration
- [x] Create `add_custom_fields_column_to_entities` migration (adds JSON column to leads, customers, organizations, contacts, deals)
- [x] Create `CustomFieldDefinition` model with scopes
- [x] Create `HasCustomFields` trait, apply to Lead, Customer, Organization, Contact, Deal
- [x] `StoreCustomFieldDefinitionRequest` / `UpdateCustomFieldDefinitionRequest`
- [x] `CustomFieldDefinitionResource`
- [x] `CustomFieldService` (list, create, update, delete, validate values, build filter rules)
- [x] `CustomFieldDefinitionController` (index, store, show, update, destroy)
- [x] `CustomFieldDefinitionPolicy`
- [x] Integrate custom field validation into entity store/update requests
- [x] Add `cf_*` filter support to entity filter pipelines
- [x] Register routes
- [x] `CustomFieldTest`

---

## Phase 23: Lead Scoring

**Goal:** Auto-score leads based on profile, engagement, and pipeline position.

- [x] Create `add_scoring_columns_to_leads_table` migration
- [x] `LeadScoringService` (calculate, recalculateAll, recalculateStale)
- [x] Add lead scoring config to `config/crm.php`
- [x] Integrate scoring into `LeadService` (recalculate on create, update, status change)
- [x] Add `score` and `recalculateScore` actions to `LeadController`
- [x] Include `score`, `score_breakdown`, `scored_at` in `LeadResource`
- [x] Add `score_min`, `score_max` to `LeadFilter`
- [x] Create `RecalculateLeadScores` command, register in scheduler
- [x] `LeadScoringTest`

---

## Phase 24: Products / Services Catalog

**Goal:** Product catalog with deal line items.

- [x] Create `products` migration
- [x] Create `product_categories` migration
- [x] Create `product_categories` table
- [x] Create `deal_items` migration
- [x] Create `ProductCategory` model with self-referencing hierarchy, scopes
- [x] Create `Product` model with scopes
- [x] Create `DealItem` model with auto-computed subtotal
- [x] Add `items()` and `products()` relationships to Deal model
- [x] `StoreProductRequest` / `UpdateProductRequest`
- [x] `StoreProductCategoryRequest` / `UpdateProductCategoryRequest`
- [x] `StoreDealItemRequest` / `UpdateDealItemRequest`
- [x] `ProductResource`, `ProductCategoryResource`, `DealItemResource`
- [x] `ProductCategoryService` (tree, list, create, update, delete, reorder)
- [x] `ProductService` (list, create, update, delete)
- [x] `DealItemService` (list, add, update, remove, syncDealValue)
- [x] `ProductCategoryController` (index, tree, store, show, update, destroy, reorder)
- [x] `ProductController`, `DealItemController`
- [x] `ProductCategoryPolicy`, `ProductPolicy`
- [x] Register routes
- [x] `ProductTest`, `ProductCategoryTest`, `DealItemTest`

---

## Phase 25: Webhooks

**Goal:** Event-driven HTTP callbacks for external integrations.

- [x] Create `webhook_endpoints` migration
- [x] Create `webhook_deliveries` migration
- [x] Create `WebhookEndpoint` model with scopes
- [x] Create `WebhookDelivery` model
- [x] `StoreWebhookEndpointRequest` / `UpdateWebhookEndpointRequest`
- [x] `WebhookEndpointResource`, `WebhookDeliveryResource`
- [x] `WebhookService` (CRUD, rotateSecret, deliveries, redeliver)
- [x] `WebhookDispatcher` (dispatch events to matching endpoints)
- [x] `DispatchWebhookJob` (queued HTTP POST with HMAC signing, retry logic)
- [x] `WebhookEndpointController` (index, store, show, update, destroy, rotateSecret, deliveries, redeliver)
- [x] `WebhookEndpointPolicy`
- [x] Integrate `WebhookDispatcher` calls into service layer methods
- [x] Add webhook delivery pruning to `PruneAuditLogs` command
- [x] Register routes
- [x] `WebhookTest`

---

---

## Phase 27: Notes / Comments

**Goal:** Polymorphic threaded notes with @mentions.

- [x] Create `notes` migration
- [x] Create `Note` model with self-referencing replies
- [x] Create `HasNotes` trait, apply to Lead, Customer, Organization, Contact, Deal
- [x] `StoreNoteRequest` / `UpdateNoteRequest`
- [x] `NoteResource` (with nested replies)
- [x] `NoteService` (list, create, update, delete, togglePin, extract @mentions)
- [x] `MentionedInNoteNotification`
- [x] `NoteController` (index, store, update, destroy, togglePin)
- [x] `NotePolicy`
- [x] Register routes
- [x] `NoteTest`

---

## Phase 28: Activity Timeline

**Goal:** Unified per-entity timeline aggregating all event sources.

- [x] `TimelineService` (aggregate from activities, audit_logs, notes, attachments, tasks, deals, campaigns, submissions)
- [x] `TimelineEventResource` (normalized event format)
- [x] `TimelineController` (index with type, date, user filters)
- [x] Register routes
- [x] `TimelineTest`

---

## Phase 29: Feature Tests (Pest)

**Goal:** Full test coverage for all endpoints.

- [x] `OrganizationTest` — CRUD, search, filter, soft-delete, permission checks
- [x] `LeadTest` — CRUD, status transitions (valid + invalid), assign, filter, permission checks
- [x] `LeadActivityTest` — CRUD, complete action
- [x] `ContactTest` — CRUD, nested under organization
- [x] `DashboardTest` — aggregated stats, activity feed
- [x] `CustomerTest` — CRUD, auto-creation on lead won, filters, health score, permission checks
- [x] `CustomerActivityTest` — CRUD, complete with outcomes, health score adjustment
- [x] `DealTest` — CRUD, auto-creation on lead won, contract_value sync, line items
- [x] `ReportTest` — user performance, company, customer reports
- [x] `NotificationTest` — list, read, mark-all-read, scheduled commands
- [x] `TaskTest` — CRUD, complete, polymorphic linking, mySummary
- [x] `TagTest` — CRUD, sync tags, filter by tag
- [x] `EmailTemplateTest` — CRUD, preview, placeholder extraction
- [x] `CampaignTest` — CRUD, send, schedule, cancel, recipient tracking
- [x] `AuditLogTest` — log creation on CRUD, filtering, entity history
- [x] `AttachmentTest` — upload, download, delete, polymorphic linking
- [x] `ImportExportTest` — CSV import queue, export streaming, templates
- [x] `SearchTest` — cross-entity search, permission scoping
- [x] `UserSettingsTest` — settings, avatar, password change
- [x] `UserManagementTest` — admin CRUD, role assignment, activate/deactivate
- [x] `CustomFieldTest` — definition CRUD, entity value validation, filtering
- [x] `LeadScoringTest` — score calculation, recency decay, scheduled recalculation
- [x] `ProductTest` — CRUD, active/inactive toggle
- [x] `ProductCategoryTest` — CRUD, hierarchy, reorder, delete-blocked-if-products
- [x] `DealItemTest` — add/update/remove items, subtotal calculation
- [x] `WebhookTest` — endpoint CRUD, delivery dispatch, signature verification, retry
- [x] `NoteTest` — CRUD, threading, @mentions, pin/unpin
- [x] `TimelineTest` — aggregation, filtering, permission scoping
- [x] `AuthorizationTest` — role/permission enforcement across endpoints

---

### Phase 30 — Async Jobs / Polling

Generic, reusable background-job polling system (shared-hosting compatible — no WebSockets).

- [x] Migration: `create_async_jobs_table`
- [x] Model: `AsyncJob` (statuses, casts, helper methods, scopes)
- [x] Job: `ProcessAsyncJob` (resolves service from container, runs method, writes result/error)
- [x] Service: `AsyncJobService` (`dispatch`, `find`, `listForUser`)
- [x] Resource: `AsyncJobResource` (conditional fields for finished/failed)
- [x] Controller: `AsyncJobController` (`index`, `show`)
- [x] Policy: `AsyncJobPolicy` (own jobs + `manage-users` override)
- [x] Factory: `AsyncJobFactory` (states: `processing`, `completed`, `failed`)
- [x] Routes: `GET /async-jobs`, `GET /async-jobs/{asyncJob}`
- [x] Report integration: `POST /reports/*/async` endpoints in `ReportController`
- [x] Tests: `AsyncJobTest` (15 tests — submission, polling, authorization, ProcessAsyncJob)

### Phase 31 — Async Report Exports

Queued Excel/PDF export generation with file download via polling.

- [x] Service: `ReportExportService` (writes xlsx/pdf to disk, returns file metadata)
- [x] Controller: 3 `POST /reports/*/export/async` endpoints in `ReportController`
- [x] Controller: `GET /async-jobs/{id}/download` endpoint in `AsyncJobController`
- [x] Routes: async export POST routes + download GET route
- [x] i18n: `async_job_not_ready` and `file_not_found` messages (en + ar)
- [x] Tests: `AsyncExportTest` (17 tests — submission, download, authorization, file generation)
- [x] Spec updates: 02, 06, 08, 09, 11
