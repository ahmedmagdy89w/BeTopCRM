# CRM Frontend Specification

> Vue 3 + Vite frontend integrated into this Laravel 13 application. The frontend is bootstrapped from `resources/js/main.ts` and consumes the API documented in this directory.
>
> Deal and DealItem sections retained later in this historical specification are superseded. The current frontend has no Deal routes, service, permissions, or product tab; contract workflows and line items use Opportunities.
>
> **Current model:** `views/leads/*`, `views/organizations/*`, `views/contacts/*` and their legacy services are removed. Customers are unified (`type`
> individual|company, `is_lead` Leads/Customers tabs, JSON contacts/addresses editors). New
> `views/opportunities/OpportunityBoardView.vue` (vuedraggable Kanban: 4 open-stage columns + closed toggle,
> assignee filter + avatars, derived `is_open` — no DB column) plus `OpportunityListView.vue` (table with
> open/closed + stage + assignee filters) and `OpportunityFormView.vue` (nested + global create with searchable
> `CustomerPicker` + single-modal quick-add for lead/customer). Global route `GET /opportunities`
> backs the board; nested `customers/:id/opportunities` backs the customer-detail tab.
> New `views/opportunities/OpportunityDetailView.vue` (route `opportunities.show`,
> `customers/:customerId/opportunities/:id`): header with stage-change dropdown, tabs Details | Activities
> (inline log/complete/delete) | Products | Notes | Timeline | Attachments, plus Quotation | Invoice tabs
> (permission- and feature-flag-gated) with inline PDF preview, download, and WhatsApp sharing. Board cards, table rows, and the customer-detail
> opportunities tab link to it. The Products tab uses an invoice-style line-item table in insertion order: users add one or more
> draft rows from the bottom **Add product** action, then save the selected rows together. Catalog prices are
> prefilled into editable unit-price fields; each line has a Discount `%`/amount toggle and shows its product tax badge with computed line tax;
> line subtotals and the live subtotal/discount/tax/total footer update in the opportunity currency, and
> saved lines can be edited or detached. Product suggestions hide their catalog prices, exclude products that
> are already attached or selected in another draft row, and render outside the horizontally scrollable table so
> the dropdown is not clipped.
> The Attachments tab embeds `AttachmentList` with an eye-button preview popup (images inline, PDFs in an iframe, other types download-only) and a footer Close button.
> `FormFileUpload` labels its button `common.upload` ("Upload"/"رفع"), not the data-import term.
> Products also have `ProductDetailView` (`products.show`) with Details | Opportunities tabs. Opportunity rows are plain text; products do not expose notes, timeline, or attachment tabs.
> `views/opportunities/SalesDocumentsView.vue` is the global Sales Documents page (route `salesDocuments`,
> `/sales-documents`). It combines paginated quotation and invoice lists from `GET /quotations` and `GET /invoices`,
> shows only tabs the user may view, supports document search/status/date filters and sortable totals/status, and opens
> inline PDF previews or downloads from the linked opportunity.
>
> Customer and opportunity detail views share `WhatsAppSendModal.vue` for editable outbound customer messages and
> quotation/invoice sharing. Click-to-Chat is the default; the optional Cloud API path is available only when configured.
> Outbound activity is logged in the CRM, but Click-to-Chat cannot confirm actual delivery. Inbound messages are not
> currently received.

---


## Table of Contents

1. [Technical Stack](#1-technical-stack)
2. [Project Structure](#2-project-structure)
3. [Architecture & Principles](#3-architecture--principles)
4. [Routing](#4-routing)
5. [Layouts](#5-layouts)
6. [Reusable Base Components](#6-reusable-base-components)
7. [DynamicForm & Quick-Add Modal](#7-dynamicform--quick-add-modal)
8. [Pinia Stores](#8-pinia-stores)
9. [Services Layer (API)](#9-services-layer-api)
10. [Composables](#10-composables)
11. [Feature Modules](#11-feature-modules)
12. [Internationalization (i18n)](#12-internationalization-i18n)
13. [RTL / LTR Support](#13-rtl--ltr-support)
14. [Authentication & Authorization](#14-authentication--authorization)
15. [Notifications (Real-Time)](#15-notifications-real-time)
16. [Error Handling](#16-error-handling)
17. [Implementation Phases](#17-implementation-phases)

---

## 1. Technical Stack

| Concern | Library | Version |
|---|---|---|
| Framework | Vue 3 (Composition API + `<script setup>`) | 3.5+ |
| Build Tool | Vite | 7+ |
| State Management | Pinia | 3+ |
| HTTP Client | Axios | 1.x |
| Routing | Vue Router | 4.x |
| CSS Framework | Bootstrap 5 | 5.3+ |
| Icons | Bootstrap Icons | 1.11+ |
| Internationalisation | Vue I18n | 10.x |
| Language | TypeScript (strict) | 5.9+ |
| Form Validation | VeeValidate + Zod | VeeValidate 4.x, Zod 3.x |
| Date Utilities | date-fns | 4.x |
| Charts | Chart.js + vue-chartjs | 4.x / 5.x |
| Toast/Alerts | vue-toastification | 2.x |
| File Upload | Native `<input type="file">` + Axios multipart |  |

### NPM Install Command (reference)

```bash
npm install vue-router@4 pinia axios bootstrap bootstrap-icons vue-i18n@10 \
  vee-validate zod @vee-validate/zod date-fns chart.js vue-chartjs vue-toastification
```

---

## 2. Project Structure

```
resources/js/
├── App.vue
├── main.ts
├── assets/
│   ├── styles/
│   │   ├── variables.css             # CSS custom-property overrides + CRM palette
│   │   ├── rtl.css                  # RTL-specific overrides
│   │   ├── components.css           # Custom component styles
│   │   └── main.css                 # Entry — imports Bootstrap + overrides
│   └── images/
│       └── logo.svg
│
├── router/
│   └── index.ts                     # Vue Router config, route guards
│
├── i18n/
│   ├── index.ts                     # Vue I18n setup
│   └── locales/
│       ├── en.json                  # English translations
│       └── ar.json                  # Arabic translations
│
├── types/
│   ├── index.ts                     # Re-exports
│   ├── models.ts                    # Entity interfaces (Lead, Organization, Customer…)
│   ├── api.ts                       # API response wrappers, pagination meta
│   ├── forms.ts                     # Form field definitions, DynamicForm types
│   └── enums.ts                     # Status enums, constant value types
│
├── services/
│   ├── api.ts                       # Axios instance, interceptors, base config
│   ├── authService.ts
│   ├── customerService.ts
│   ├── dealService.ts
│   ├── taskService.ts
│   ├── tagService.ts
│   ├── notificationService.ts
│   ├── reportService.ts
│   ├── asyncJobService.ts           # Async job polling + download URL
│   ├── dashboardService.ts
│   ├── quotationService.ts
│   ├── taxService.ts                  # Taxes CRUD + active_only filter
│   ├── invoiceService.ts
│   ├── campaignService.ts
│   ├── attachmentService.ts
│   ├── importExportService.ts
│   ├── searchService.ts
│   ├── userService.ts
│   ├── customFieldService.ts
│   ├── productService.ts
│   ├── productCategoryService.ts
│   ├── webhookService.ts
│   ├── noteService.ts
│   ├── timelineService.ts
│   ├── whatsappService.ts
│   └── auditLogService.ts
│
├── stores/
│   ├── auth.ts                      # User session and permissions
│   ├── ui.ts                        # Sidebar state, locale, theme
│   ├── notifications.ts             # Unread count, notification list
│   ├── tags.ts                      # Global tag list (small dataset, loaded once)
│   ├── customFields.ts             # Cached model/category groups and definitions
│   └── companySettings.ts           # Company settings incl. product labels (fetched once, patched on save)
│
├── composables/
│   ├── useDataTable.ts              # Pagination, sorting, filtering, debounced search
│   ├── useCrudForm.ts               # Create/edit form state, submit, validation
│   ├── useCustomFields.ts           # Resolve model/category definitions into DynamicForm schema
│   ├── useConfirmDialog.ts          # Confirm-before-delete pattern
│   ├── usePermission.ts             # Check current user's permissions
│   ├── useToast.ts                  # Toast notification helper
│   ├── useAsyncReport.ts            # Async report/export polling with job progress
│   ├── useDefaultDateRange.ts       # Last-N-days date range for reports/dashboard (default 30)
│   └── useProductLabels.ts          # Company product terminology (singular/plural/report title)
│   └── useFeatures.ts               # Quotations/invoices feature flags (showQuotations/showInvoices)
│
├── components/
│   ├── base/
│   │   ├── BaseButton.vue
│   │   ├── BaseCard.vue
│   │   ├── BaseDataTable.vue
│   │   ├── BaseBadge.vue
│   │   ├── BaseModal.vue
│   │   ├── BaseSpinner.vue
│   │   ├── BasePagination.vue
│   │   ├── BaseEmptyState.vue
│   │   ├── BaseAlert.vue
│   │   └── BaseDropdown.vue
│   │
│   ├── form/
│   │   ├── DynamicForm.vue          # Renders fields from schema
│   │   ├── FormField.vue            # Single field renderer (text, select, date…)
│   │   ├── FormSelect.vue           # Select with search + Quick-Add button
│   │   ├── FormDatePicker.vue
│   │   ├── FormFileUpload.vue
│   │   ├── FormTagInput.vue         # Tag selector with color badges
│   │   ├── FormRichText.vue         # Textarea or markdown editor
│   │   └── QuickAddModal.vue        # Generic Quick-Add modal wrapper
│   │
│   ├── layout/
│   │   ├── AppLayout.vue            # Main authenticated shell
│   │   ├── Sidebar.vue
│   │   ├── Topbar.vue
│   │   ├── BreadcrumbBar.vue
│   │   └── LocaleSwitcher.vue
│   │
│   ├── shared/
│   │   ├── GlobalSearch.vue         # Header search bar (Ctrl+K)
│   │   ├── NotificationDropdown.vue
│   │   ├── UserMenu.vue
│   │   ├── StatusBadge.vue          # Lead/Customer/Deal status with colour
│   │   ├── HealthScoreBar.vue       # Customer health score visual
│   │   ├── ScoreBadge.vue           # Lead score display
│   │   ├── TagList.vue              # Display tag colour badges
│   │   ├── TimelineView.vue         # Unified activity timeline
│   │   ├── TimelineEvent.vue        # Single timeline event card
│   │   ├── AttachmentList.vue
│   │   ├── NoteThread.vue           # Threaded notes with replies
│   │   ├── NoteEditor.vue           # Note creation/edit with @mentions
│   │   ├── WhatsAppSendModal.vue    # Prepare, edit, and initiate an outbound WhatsApp message
│   │   ├── AuditLogTable.vue
│   │   ├── ImportProgressCard.vue
│   │   ├── CustomFieldValue.vue
│   │   ├── CustomFieldFilterBar.vue
│   │   ├── CustomFieldDefinitionEditor.vue
│   │   └── ConfirmDialog.vue
│   │
│   └── dashboard/
│       ├── PipelineChart.vue        # Horizontal funnel bar chart
│       ├── RevenueChart.vue         # Line/bar chart (monthly revenue)
│       ├── TaskSummaryCard.vue
│       ├── CustomerHealthCard.vue
│       ├── TopPerformersCard.vue
│       ├── CampaignSummaryCard.vue
│       └── ActivityFeed.vue
│
├── views/
│   ├── auth/
│   │   ├── LoginView.vue
│   │   └── ForgotPasswordView.vue
│   │
│   ├── dashboard/
│   │   └── DashboardView.vue
│   │
│   ├── organizations/
│   │   ├── OrganizationListView.vue
│   │   ├── OrganizationDetailView.vue
│   │   └── OrganizationFormView.vue
│   │
│   ├── leads/
│   │   ├── LeadListView.vue
│   │   ├── LeadDetailView.vue
│   │   └── LeadFormView.vue
│   │
│   ├── opportunities/
│   │   ├── OpportunityBoardView.vue
│   │   ├── OpportunityListView.vue
│   │   ├── OpportunityFormView.vue
│   │   ├── OpportunityDetailView.vue
│   │   └── SalesDocumentsView.vue
│   │
│   ├── customers/
│   │   ├── CustomerListView.vue
│   │   ├── CustomerDetailView.vue
│   │   └── CustomerFormView.vue   # Edit only (no create — auto-created from won leads)
│   │
│   ├── deals/
│   │   ├── DealListView.vue       # Nested under customer detail
│   │   └── DealFormView.vue
│   │
│   ├── contacts/
│   │   └── ContactFormView.vue    # Nested under organization detail
│   │
│   ├── tasks/
│   │   ├── TaskListView.vue
│   │   └── TaskFormView.vue
│   │
│   ├── reports/
│   │   ├── UserPerformanceReport.vue
│   │   ├── CompanyReport.vue
│   │   └── CustomerReport.vue
│   │
│   ├── campaigns/
│   │   ├── CampaignListView.vue
│   │   ├── CampaignDetailView.vue
│   │   └── CampaignFormView.vue
│   │
│   ├── email-templates/
│   │   ├── TemplateListView.vue
│   │   └── TemplateFormView.vue
│   │
│   ├── tags/
│   │   └── TagListView.vue
│   │
│   ├── products/
│   │   ├── ProductListView.vue
│   │   ├── ProductDetailView.vue
│   │   ├── ProductFormView.vue
│   │   ├── TaxListView.vue              # Flat table + inline form (name/percent/active), view-taxes
│   │   └── ProductCategoryListView.vue
│   │
│   ├── webhooks/
│   │   ├── WebhookListView.vue
│   │   └── WebhookFormView.vue
│   │
│   ├── audit-logs/
│   │   └── AuditLogView.vue
│   │
│   ├── import-export/
│   │   └── ImportExportView.vue
│   │
│   ├── users/
│   │   ├── UserListView.vue
│   │   └── UserFormView.vue
│   │
│   ├── settings/
│   │   ├── UserSettingsView.vue
│   │   └── CustomFieldGroupsView.vue
│   │
│   └── errors/
│       ├── NotFoundView.vue
│       └── ForbiddenView.vue
│
└── utils/
    ├── constants.ts                 # Status maps, source values, colours
    ├── formatters.ts                # Date, currency, file-size formatters
    ├── validators.ts                # Zod schemas mirroring backend rules
    ├── permissions.ts               # Permission constants matching backend
    └── customFieldTypes.ts           # Backend field type → reusable form control map
```

---

## 3. Architecture & Principles

### SOLID in the Frontend

| Principle | Application |
|---|---|
| **S**ingle Responsibility | Each service handles one entity's API calls. Each composable handles one concern (pagination, form state, permissions). Views compose them. |
| **O**pen/Closed | `BaseDataTable` is extended via props/slots, not modified. `DynamicForm` accepts field schemas — new field types are added via `FormField`, never by editing the form component. |
| **L**iskov Substitution | All base components accept a consistent prop interface. `BaseButton` renders a `<button>` or `<router-link>` transparently depending on `to` prop. |
| **I**nterface Segregation | TypeScript interfaces are granular — `LeadListItem` (for tables) vs `LeadDetail` (for detail views). Stores expose minimal getters/actions. |
| **D**ependency Inversion | Views depend on service abstractions (typed functions), not raw Axios. Composables accept service functions as arguments where polymorphism is needed. |

### Data Flow

```
View  →  Composable (useDataTable / useCrudForm)
             ↓
         Service (API call)
             ↓
         Axios Instance (interceptors: auth token, error handling, locale header)
             ↓
         Laravel API (/api/v1/…)
```

### State Management Strategy

| Data Type | Where | Why |
|---|---|---|
| Auth (user, token, permissions) | Pinia `authStore` | Global, persisted to localStorage |
| UI (sidebar, locale, theme) | Pinia `uiStore` | Global, persisted to localStorage |
| Notifications (unread count) | Pinia `notificationStore` | Global, polled |
| Tags (list) | Pinia `tagStore` | Small dataset, loaded once |
| Custom field groups/definitions | Pinia `customFieldStore` | Loaded and cached per model or product-category scope |
| Entity lists & detail | Local component state via composables | Server-driven, not cached globally |

---

## 4. Routing

### Route Definitions

```ts
// resources/js/router/index.ts
const routes: RouteRecordRaw[] = [
  // ── Public ──
  { path: '/login',    name: 'login',    component: () => import('@/views/auth/LoginView.vue'),    meta: { guest: true } },
  { path: '/forgot-password', name: 'forgot-password', component: () => import('@/views/auth/ForgotPasswordView.vue'), meta: { guest: true } },

  // ── Authenticated ──
  {
    path: '/',
    component: () => import('@/components/layout/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '',                        name: 'dashboard',              component: () => import('@/views/dashboard/DashboardView.vue') },

      // Customers
      { path: 'customers',               name: 'customers.index',        component: () => import('@/views/customers/CustomerListView.vue'),   meta: { permission: 'view-customers' } },
      { path: 'customers/create',        name: 'customers.create',        component: () => import('@/views/customers/CustomerFormView.vue'),   meta: { permission: 'create-customers' } },
      { path: 'customers/:id',           name: 'customers.show',         component: () => import('@/views/customers/CustomerDetailView.vue'), meta: { permission: 'view-customers' } },
      { path: 'customers/:id/edit',      name: 'customers.edit',         component: () => import('@/views/customers/CustomerFormView.vue'),   meta: { permission: 'update-customers' } },

      // Opportunities
      { path: 'opportunities',           name: 'opportunities.index',     component: () => import('@/views/opportunities/OpportunityBoardView.vue'), meta: { permission: 'view-opportunities' } },
      { path: 'opportunities/table',     name: 'opportunities.table',     component: () => import('@/views/opportunities/OpportunityListView.vue'),  meta: { permission: 'view-opportunities' } },
      { path: 'sales-documents',        name: 'salesDocuments',          component: () => import('@/views/opportunities/SalesDocumentsView.vue'), meta: { permissions: ['view-quotations', 'view-invoices'] } },  // + feature-flag guard (either module enabled)
      { path: 'opportunities/create',   name: 'opportunities.createGlobal', component: () => import('@/views/opportunities/OpportunityFormView.vue'), meta: { permission: 'create-opportunities' } },
      { path: 'customers/:customerId/opportunities/:id', name: 'opportunities.show', component: () => import('@/views/opportunities/OpportunityDetailView.vue'), meta: { permission: 'view-opportunities' } },


      // Deals (nested under customer)
      { path: 'customers/:customerId/deals/create', name: 'deals.create',  component: () => import('@/views/deals/DealFormView.vue'),  meta: { permission: 'create-deals' } },
      { path: 'customers/:customerId/deals/:id/edit', name: 'deals.edit',  component: () => import('@/views/deals/DealFormView.vue'),  meta: { permission: 'update-deals' } },

      // Activities
      { path: 'activities',              name: 'activities.index',       component: () => import('@/views/activities/ActivityListView.vue'),   meta: { permission: 'view-customers' } },

      // Tasks
      { path: 'tasks',                   name: 'tasks.index',            component: () => import('@/views/tasks/TaskListView.vue') },
      { path: 'tasks/create',            name: 'tasks.create',           component: () => import('@/views/tasks/TaskFormView.vue') },
      { path: 'tasks/:id/edit',          name: 'tasks.edit',             component: () => import('@/views/tasks/TaskFormView.vue') },

      // Reports
      { path: 'reports/user-performance', name: 'reports.userPerformance', component: () => import('@/views/reports/UserPerformanceReport.vue'), meta: { permission: 'view-reports' } },
      { path: 'reports/company',          name: 'reports.company',         component: () => import('@/views/reports/CompanyReport.vue'),         meta: { permission: 'view-reports' } },
      { path: 'reports/customer',         name: 'reports.customer',        component: () => import('@/views/reports/CustomerReport.vue'),        meta: { permission: 'view-reports' } },
      { path: 'ai-assistant',              name: 'aiAssistant',             component: () => import('@/views/ai/AiChatView.vue'),                 meta: { permission: 'create-ai-chats' } },

      // Campaigns
      { path: 'campaigns',               name: 'campaigns.index',        component: () => import('@/views/campaigns/CampaignListView.vue'),   meta: { permission: 'view-campaigns' } },
      { path: 'campaigns/create',        name: 'campaigns.create',       component: () => import('@/views/campaigns/CampaignFormView.vue'),   meta: { permission: 'create-campaigns' } },
      { path: 'campaigns/:id',           name: 'campaigns.show',         component: () => import('@/views/campaigns/CampaignDetailView.vue'), meta: { permission: 'view-campaigns' } },
      { path: 'campaigns/:id/edit',      name: 'campaigns.edit',         component: () => import('@/views/campaigns/CampaignFormView.vue'),   meta: { permission: 'update-campaigns' } },

      // Email Templates
      { path: 'email-templates',          name: 'templates.index',       component: () => import('@/views/email-templates/TemplateListView.vue'), meta: { permission: 'create-templates' } },
      { path: 'email-templates/create',   name: 'templates.create',      component: () => import('@/views/email-templates/TemplateFormView.vue'), meta: { permission: 'create-templates' } },
      { path: 'email-templates/:id/edit', name: 'templates.edit',        component: () => import('@/views/email-templates/TemplateFormView.vue'), meta: { permission: 'update-templates' } },

      // Tags
      { path: 'tags',                    name: 'tags.index',             component: () => import('@/views/tags/TagListView.vue'), meta: { permission: 'create-tags' } },

      // Products
      { path: 'products',                name: 'products.index',         component: () => import('@/views/products/ProductListView.vue'),          meta: { permission: 'view-products' } },
      { path: 'products/create',         name: 'products.create',        component: () => import('@/views/products/ProductFormView.vue'),          meta: { permission: 'create-products' } },
      { path: 'products/:id',            name: 'products.show',           component: () => import('@/views/products/ProductDetailView.vue'),       meta: { permission: 'view-products' } },
      { path: 'products/:id/edit',       name: 'products.edit',          component: () => import('@/views/products/ProductFormView.vue'),          meta: { permission: 'update-products' } },
      { path: 'products/categories',     name: 'productCategories.index', component: () => import('@/views/products/ProductCategoryListView.vue'), meta: { permission: 'view-products' } },
      { path: 'products/taxes',          name: 'taxes.index',             component: () => import('@/views/products/TaxListView.vue'),          meta: { permission: 'view-taxes' } },

      // Webhooks
      { path: 'webhooks',                name: 'webhooks.index',         component: () => import('@/views/webhooks/WebhookListView.vue'),  meta: { permission: 'view-webhooks' } },
      { path: 'webhooks/create',         name: 'webhooks.create',        component: () => import('@/views/webhooks/WebhookFormView.vue'),  meta: { permission: 'create-webhooks' } },
      { path: 'webhooks/:id/edit',       name: 'webhooks.edit',          component: () => import('@/views/webhooks/WebhookFormView.vue'),  meta: { permission: 'update-webhooks' } },

      // Audit Logs
      { path: 'audit-logs',              name: 'auditLogs.index',        component: () => import('@/views/audit-logs/AuditLogView.vue'), meta: { permission: 'view-audit-logs' } },

      // Import / Export
      { path: 'import-export',           name: 'importExport',           component: () => import('@/views/import-export/ImportExportView.vue'), meta: { permissions: ['import-data', 'export-data'] } },

      // User Management
      { path: 'users',                   name: 'users.index',            component: () => import('@/views/users/UserListView.vue'),  meta: { permission: 'view-users' } },
      { path: 'users/create',            name: 'users.create',           component: () => import('@/views/users/UserFormView.vue'),  meta: { permission: 'create-users' } },
      { path: 'users/:id/edit',          name: 'users.edit',             component: () => import('@/views/users/UserFormView.vue'),  meta: { permission: 'update-users' } },

      // Settings (self-service)
      { path: 'settings',                name: 'settings',               component: () => import('@/views/settings/UserSettingsView.vue') },
      { path: 'settings/custom-fields',  name: 'custom-fields.index',    component: () => import('@/views/settings/CustomFieldGroupsView.vue'), meta: { permissions: ['create-custom-fields', 'update-custom-fields', 'delete-custom-fields'] } },

      // Error pages
      { path: 'forbidden',               name: 'forbidden',              component: () => import('@/views/errors/ForbiddenView.vue') },
    ],
  },

  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/views/errors/NotFoundView.vue') },
]
```

### Navigation Guards

```ts
router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // Redirect authenticated users away from guest pages
  if (to.meta.guest && auth.isAuthenticated) return { name: 'dashboard' }

  // Require auth
  if (to.meta.requiresAuth && !auth.isAuthenticated) return { name: 'login', query: { redirect: to.fullPath } }

  // Permission check: one `permission`, or any permission in `permissions`
  const requiredPermission = to.meta.permission
  const requiredPermissions = to.meta.permissions
  const hasAccess = requiredPermission
    ? auth.isAdmin || auth.hasPermission(requiredPermission)
    : requiredPermissions?.length
      ? auth.isAdmin || requiredPermissions.some(permission => auth.hasPermission(permission))
      : true

  if ((requiredPermission || requiredPermissions?.length) && !hasAccess) return { name: 'forbidden' }
})
```

---

## 5. Layouts

### AppLayout.vue

The main authenticated shell — sidebar + topbar + content area.

```
┌─────────────────────────────────────────────────┐
│  Topbar  [ GlobalSearch ]  [ Notifications ] [User] │
├────────┬────────────────────────────────────────┤
│        │                                        │
│ Side   │   BreadcrumbBar                        │
│ bar    │   ┌────────────────────────────────┐   │
│        │   │       <router-view />          │   │
│ (nav)  │   │                                │   │
│        │   │                                │   │
│        │   └────────────────────────────────┘   │
├────────┴────────────────────────────────────────┤
```

**Sidebar** — collapsible, shows navigation grouped by section:

| Section | Items | Visibility |
|---|---|---|
| Main | Dashboard | Always |
| CRM | Leads, Customers, Organizations | Always |
| Sales | Products, Taxes, Sales Documents | `view-products` / `view-taxes`; Sales Documents requires `view-quotations` or `view-invoices` **and** the matching feature flag (`showSalesDocuments`) |
| Marketing | Campaigns, Email Templates | `view-campaigns` / `create-templates` |
| Productivity | Tasks, Tags | Always / `create-tags` |
| Reports | User Performance, Company, Customer, AI Assistant | `view-reports` / `create-ai-chats` (assistant) |
| System | Users, Audit Logs, Webhooks, Custom Fields, Import/Export | Custom Fields requires any of `create-/update-/delete-custom-fields` |
| ─── | Settings | Always (bottom) |

**Topbar** — Contains:
- Logo / brand
- `GlobalSearch` component (keyboard shortcut `Ctrl+K` / `⌘+K`)
- `NotificationDropdown` with unread badge
- `LocaleSwitcher` (EN / AR toggle)
- `UserMenu` (profile, settings, logout)

**RTL Note:** When locale is `ar`, `<html dir="rtl">` is set, Bootstrap's RTL bundle is loaded, and the sidebar flips to the right side automatically via Bootstrap's flexbox utilities.

---

## 6. Reusable Base Components

### 6.1 BaseDataTable

A configurable, reusable data table used by all list views.

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `columns` | `Column[]` | required | Column definitions |
| `rows` | `any[]` | `[]` | Current page of data |
| `loading` | `boolean` | `false` | Show loading overlay |
| `total` | `number` | `0` | Total records (for pagination) |
| `perPage` | `number` | `15` | Items per page |
| `currentPage` | `number` | `1` | Active page |
| `sortBy` | `string` | `''` | Currently sorted column key |
| `sortDir` | `'asc' \| 'desc'` | `'desc'` | Sort direction |
| `selectable` | `boolean` | `false` | Show row checkboxes |
| `searchable` | `boolean` | `true` | Show search input |
| `searchPlaceholder` | `string` | `t('common.search')` | Placeholder text |
| `emptyMessage` | `string` | `t('common.noData')` | Empty state text |
| `emptyIcon` | `string` | `'bi-inbox'` | Empty state icon |

**Column Definition (TypeScript):**

```ts
interface Column {
  key: string            // Field path in row object (supports dot notation)
  label: string          // Display header (i18n key or string)
  sortable?: boolean     // Enable sorting on this column (default: false)
  filterable?: boolean   // Show filter dropdown for this column
  filterOptions?: { label: string; value: string }[]  // Dropdown filter option list
  type?: 'text' | 'date' | 'currency' | 'badge' | 'custom'
  align?: 'start' | 'center' | 'end'
  width?: string         // CSS width (e.g. '120px', '15%')
  visible?: boolean      // Show/hide column (default: true)
}
```

**Emits:**

| Event | Payload | Description |
|---|---|---|
| `update:currentPage` | `number` | Page changed |
| `update:perPage` | `number` | Page size changed |
| `update:sortBy` | `string` | Sort column changed |
| `update:sortDir` | `'asc' \| 'desc'` | Sort direction toggled |
| `search` | `string` | Debounced search text (300ms) |
| `filter` | `Record<string, string>` | Active filter values |
| `row-click` | `row: any` | Row clicked |
| `selection-change` | `row[]` | Selected rows changed |

**Slots:**

| Slot | Scope | Description |
|---|---|---|
| `header-actions` | — | Actions above the table (create button, export…) |
| `cell-{key}` | `{ row, value }` | Custom render for a specific column |
| `row-actions` | `{ row }` | Action buttons column (edit, delete…) |
| `filters` | — | Custom filter controls above table |
| `empty` | — | Override empty state |
| `bulk-actions` | `{ selected }` | Bulk action bar when rows are selected |

**Behaviour:**
- Sort: clicking a sortable column header emits `update:sortBy` / `update:sortDir`. Shows `bi-sort-up` / `bi-sort-down` icons.
- Filter: column filters render as Bootstrap dropdowns below the header.
- Search: text input above the table, debounced to 300ms before emitting.
- Pagination: renders `BasePagination` below the table.
- Loading: semi-transparent overlay with `BaseSpinner`.
- Responsive: horizontal scroll on small screens via `table-responsive`.

### 6.2 BaseButton

A button component that respects the CRM design system.

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `variant` | `'primary' \| 'secondary' \| 'success' \| 'danger' \| 'warning' \| 'info' \| 'outline-primary' \| 'outline-secondary' \| 'link'` | `'primary'` | Bootstrap button variant |
| `size` | `'sm' \| 'md' \| 'lg'` | `'md'` | Button size |
| `icon` | `string` | — | Bootstrap icon class (e.g. `'bi-plus'`) |
| `iconPosition` | `'start' \| 'end'` | `'start'` | Icon placement |
| `loading` | `boolean` | `false` | Show spinner, disable button |
| `disabled` | `boolean` | `false` | Disabled state |
| `to` | `RouteLocationRaw` | — | If provided, renders `<router-link>` instead of `<button>` |
| `type` | `'button' \| 'submit' \| 'reset'` | `'button'` | HTML button type |
| `block` | `boolean` | `false` | Full-width |
| `permission` | `string` | — | If set, hides button when user lacks this permission |

**Slots:** `default` (label text), `icon` (override icon).

### 6.3 BaseCard

Consistent card wrapper used for detail sections, dashboard widgets, and form groups.

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `title` | `string` | — | Card header title |
| `subtitle` | `string` | — | Small text below title |
| `icon` | `string` | — | Bootstrap icon in header |
| `collapsible` | `boolean` | `false` | Allow collapse/expand |
| `defaultCollapsed` | `boolean` | `false` | Start collapsed |
| `loading` | `boolean` | `false` | Show skeleton/spinner |
| `noPadding` | `boolean` | `false` | Remove body padding (for tables) |

**Slots:** `header-actions`, `default` (body), `footer`.

### 6.4 BaseModal

Bootstrap 5 modal wrapper.

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `modelValue` | `boolean` | `false` | v-model show/hide |
| `title` | `string` | — | Modal title |
| `size` | `'sm' \| 'md' \| 'lg' \| 'xl'` | `'md'` | Modal size |
| `scrollable` | `boolean` | `false` | Scrollable body |
| `staticBackdrop` | `boolean` | `false` | Prevent close on backdrop click |
| `hideFooter` | `boolean` | `false` | Hide default footer |

**Slots:** `default` (body), `footer`.

**Emits:** `update:modelValue`, `close`.

### 6.5 BasePagination

Wraps Bootstrap pagination with page size selector.

**Props:** `total`, `perPage`, `currentPage`, `perPageOptions` (default `[15, 25, 50, 100]`).

**Emits:** `update:currentPage`, `update:perPage`.

---

## 7. DynamicForm & Quick-Add Modal

### 7.1 DynamicForm

Renders a form from a JSON schema. Every entity create/edit view uses this component.

**Props:**

| Prop | Type | Description |
|---|---|---|
| `schema` | `FormFieldSchema[]` | Array of field definitions |
| `modelValue` | `Record<string, any>` | Current form values (v-model) |
| `errors` | `Record<string, string[]>` | Server-side validation errors |
| `loading` | `boolean` | Disable all inputs while submitting |
| `columns` | `1 \| 2 \| 3` | Grid layout columns (default: 1) |
| `customFields` | `CustomFieldDefinition[]` | Backend custom field definitions — appended to schema dynamically |

**FormFieldSchema:**

```ts
interface FormFieldSchema {
  key: string                          // Field key in modelValue
  label: string                        // Display label (i18n key)
  type: 'text' | 'email' | 'tel' | 'number' | 'textarea' | 'select' | 'color'
        | 'multi_select' | 'date' | 'datetime' | 'boolean' | 'file'
        | 'tag' | 'url' | 'password' | 'hidden'
  required?: boolean
  placeholder?: string
  helpText?: string
  disabled?: boolean
  visible?: boolean | ((values: Record<string, any>) => boolean)
  rules?: ZodType                      // Zod validation schema
  colSpan?: 1 | 2 | 3                 // Column span in grid
  step?: number | 'any'                // Numeric step (number: 1, decimal: 0.01)
  multiple?: boolean                   // Native multiple selection

  // Select-specific
  options?: { label: string; value: any }[]
  optionsUrl?: string                  // Fetch options from API endpoint
  optionsLabelKey?: string             // Key to use as label from API response
  optionsValueKey?: string             // Key to use as value from API response

  // Relationship + Quick-Add
  quickAdd?: {
    entity: string                     // e.g. 'organization', 'user'
    formSchema: FormFieldSchema[]      // Minimal form schema for quick creation
    service: string                    // Service key for submitting
    permission?: string                // Required permission to show quick-add
  }
}
```

**Behaviour:**
- `DynamicForm` iterates `schema` and renders a `FormField` for each.
- `FormField` delegates to the appropriate sub-component (`FormSelect`, `FormDatePicker`, etc.) based on `type`.
- When `type === 'select'` and `quickAdd` is defined, `FormSelect` shows a small `+` button next to the dropdown.
- Clicking `+` opens `QuickAddModal` with the `quickAdd.formSchema`.
- On successful Quick-Add submission, the new item is prepended to the select options and auto-selected.
- Custom fields from the backend (`customFields` prop) are converted to `FormFieldSchema` objects and appended at the end of the form.
- Custom fields bind dot paths such as `custom_fields.size` and `category_custom_fields.size`; value reads/writes and backend 422 errors use those paths.

### 7.2 QuickAddModal

**Props:**

| Prop | Type | Description |
|---|---|---|
| `modelValue` | `boolean` | v-model visibility |
| `title` | `string` | Modal title (e.g. "Quick Add Organization") |
| `schema` | `FormFieldSchema[]` | Form schema for the quick-add entity |
| `submitFn` | `(data: any) => Promise<any>` | Service function to call on submit |

**Emits:** `created` (newly created entity), `update:modelValue`.

**Usage Example (Customer Form):**

The `customer` relationship field in an opportunity form schema:

```ts
{
  key: 'customer_id',
  label: 'opportunities.customer',
  type: 'select',
  optionsUrl: '/api/v1/customers?per_page=100',
  optionsLabelKey: 'name',
  optionsValueKey: 'id',
  quickAdd: {
    entity: 'customer',
    formSchema: [
       { key: 'name', label: 'customers.name', type: 'text', required: true },
       { key: 'type', label: 'customers.type', type: 'select', required: true },
       { key: 'email', label: 'customers.email', type: 'email' },
       { key: 'phone', label: 'customers.phone', type: 'tel' },
    ],
    service: 'customerService',
    permission: 'create-customers',
  },
}
```

When the user clicks `+` beside the Customer dropdown while creating an Opportunity, a modal opens with a minimal Customer form. On submit, the Customer is created via the API and the dropdown refreshes with the new entry selected.

### 7.3 Quick-Add Relationships Map

| Parent Form | Relationship Field | Quick-Add Entity | Minimal Fields |
|---|---|---|---|
| Opportunity | `customer_id` | Customer | name, type, email, phone |
| Opportunity | `assigned_to` | — (user select, no quick-add) | — |
| Deal | `product_id` (deal items) | Product | name, unit_price, category |
| Campaign | `email_template_id` | Email Template | name, subject, body |
| Task | linked entity | — (search existing) | — |

---

## 8. Pinia Stores

### 8.1 authStore

```ts
// resources/js/stores/auth.ts
interface AuthState {
  user: User | null
  permissions: string[]
  roles: string[]
}

// Getters
isAuthenticated: boolean
hasPermission(permission: string): boolean
hasRole(role: string): boolean
isAdmin: boolean
isManager: boolean
isSalesRep: boolean

// Actions
login(email: string, password: string): Promise<void>
requestPasswordResetOtp(email: string): Promise<void>
resetPasswordWithOtp(data: ResetPasswordOtpPayload): Promise<void>
logout(): Promise<void>
  fetchMe(): Promise<void>               // GET /auth/me — refresh user + permissions
  initFromStorage(): Promise<void>      // On app boot, validate the Laravel session
```

### 8.2 uiStore

```ts
// resources/js/stores/ui.ts
interface UIState {
  sidebarCollapsed: boolean
  locale: 'en' | 'ar'
  dateFormat: string
  timezone: string
}

// Actions
toggleSidebar(): void
setLocale(locale: 'en' | 'ar'): void  // Updates <html dir>, loads locale file
setSidebarCollapsed(val: boolean): void
```

### 8.3 notificationStore

```ts
// stores/notifications.ts
interface NotificationState {
  unreadCount: number
  notifications: Notification[]
  loading: boolean
}

// Actions
fetchUnreadCount(): Promise<void>       // GET /notifications/unread-count
fetchNotifications(filters?): Promise<void>
markAsRead(id: string): Promise<void>
markAllAsRead(): Promise<void>
deleteNotification(id: string): Promise<void>
startPolling(intervalMs?: number): void // Default 60 seconds
stopPolling(): void
```

### 8.4 tagStore

```ts
// stores/tags.ts
interface TagState {
  tags: Tag[]
  loaded: boolean
}

// Actions
fetchTags(): Promise<void>             // GET /tags — load once, re-fetch on create/update/delete
createTag(data): Promise<Tag>
updateTag(id, data): Promise<Tag>
deleteTag(id): Promise<void>
```

### 8.5 customFieldStore

```ts
// stores/customFields.ts
interface CustomFieldState {
  definitions: Record<string, CustomFieldDefinition[]>  // `${entity}:entity` or `${entity}:category:${id}`
  groups: Record<string, CustomFieldGroup | null>
  loaded: Record<string, boolean>
}

// Actions
fetchForEntity(entityType: string, force?: boolean): Promise<void>
fetchForCategory(entityType: string, categoryId: number, force?: boolean): Promise<void>
fetchEntities(force?: boolean): Promise<CustomFieldEntity[]>
getForEntity(entityType: string): CustomFieldDefinition[]
getForCategory(entityType: string, categoryId: number): CustomFieldDefinition[]
```

### 8.6 companySettingsStore

```ts
// stores/companySettings.ts
interface CompanySettingsState {
  settings: CompanySettings | null
  loaded: boolean
}

// Actions
fetchSettings(force?: boolean): Promise<void>  // GET /company/settings — once, silent fallback to i18n on failure
setSettings(data: CompanySettings): void        // Patch after any settings save (instant label updates)
updateSettings(data): Promise<CompanySettings>
productLabels(): CompanySettingsProductLabels  // Raw {singular_en, plural_en, singular_ar, plural_ar} (nulls = unset)
showQuotations(): boolean  // features.quotations_enabled, default true pre-load
showInvoices(): boolean     // features.invoices_enabled, default true pre-load
```
```

---

## 9. Services Layer (API)

### 9.1 Axios Instance (`services/api.ts`)

```ts
const api = axios.create({
  baseURL: '/api/v1',
  headers: { 'Accept': 'application/json' },
  withCredentials: true,
})

// Request interceptor: attach the locale; the browser sends the Laravel session cookie.
api.interceptors.request.use((config) => {
  const ui = useUIStore()
  config.headers['Accept-Language'] = ui.locale

  return config
})

// Response interceptor: handle 401 → clear the session and redirect to login.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      useAuthStore().logout()
      router.push({ name: 'login' })
    }
    return Promise.reject(error)
  }
)
```

### 9.2 Service Pattern

Each service file exports typed functions — no class instances.

**Example: `resources/js/services/customerService.ts`**

```ts
import api from './api'
import type { Customer, CustomerDetail, PaginatedResponse, CustomerFilters } from '@/types'

export const customerService = {
  list(params: CustomerFilters & PaginationParams): Promise<PaginatedResponse<Customer>> {
    return api.get('/customers', { params }).then(r => r.data)
  },

  get(id: number): Promise<CustomerDetail> {
    return api.get(`/customers/${id}`).then(r => r.data.data)
  },

  create(data: Partial<Customer>): Promise<Customer> {
    return api.post('/customers', data).then(r => r.data.data)
  },

  update(id: number, data: Partial<Customer>): Promise<Customer> {
    return api.put(`/customers/${id}`, data).then(r => r.data.data)
  },

  delete(id: number): Promise<void> {
    return api.delete(`/customers/${id}`)
  },
}
```

All entity services follow this pattern. Nested resources include the parent ID, such as opportunities and deals under a customer.

### 9.3 Service Inventory

| Service | Backend Prefix | Key Methods |
|---|---|---|
| `authService` | `/auth` | `login`, `requestPasswordResetOtp`, `resetPasswordWithOtp`, `logout`, `me` |
| `quotationService` | `/quotations`, `/customers/{id}/opportunities/{id}/quotations` | `listGlobal`, list/create/send quotation, `previewUrl`, `pdfUrl` (`?lang=` carries the UI locale — plain navigation sends no `Accept-Language`) — create takes only `valid_until/notes/terms`; totals derive from lines. Omit `terms` to render the locale default (`opportunities.quotationDefaultTerms`); preview falls back the same way |
| `invoiceService` | `/invoices`, `/customers/{id}/opportunities/{id}/invoice` | `listGlobal`, get/create invoice, `previewUrl`, `pdfUrl` (same `?lang=` UI-locale behavior as quotations) |
| `taxService` | `/taxes` | CRUD + `list(activeOnly)` — `?active_only=1` feeds product pickers |
| `whatsappService` | `/customers/{id}/whatsapp`, `/customers/{id}/opportunities/{id}/.../whatsapp` | Prepare customer/quotation/invoice message; initiate customer/opportunity send |
| `customerService` | `/customers` | List, Show, Update, Delete + `syncTags` |

| `opportunityService` | `/opportunities`, `/customers/{id}/opportunities` | CRUD + stage, activities, products |
| `dealService` | `/customers/{id}/deals` | CRUD |
| `taskService` | `/tasks` | CRUD + `complete`, `mySummary` |
| `tagService` | `/tags` | CRUD |
| `notificationService` | `/notifications` | List, unreadCount, markRead, markAllRead, delete |
| `reportService` | `/reports` | `userPerformance`, `company`, `customer`, sync exports, async report generation, async export generation |
| `asyncJobService` | `/async-jobs` | `list`, `get`, `downloadUrl` — poll job status + download completed export files |
| `aiChatService` | `/ai-chat` | `askAsync`, `suggestedQuestions` — async analytics answers + localized starter questions |
| `dashboardService` | `/dashboard` | `index`, `activityFeed` |
| `campaignService` | `/campaigns` | CRUD + `send`, `schedule`, `cancel`, `recipients` |
| `templateService` | `/email-templates` | CRUD + `preview` |
| `attachmentService` | `/{entity}/{id}/attachments` | `list`, `upload`, `download`, `delete` |
| `importExportService` | `/import`, `/export` | `startImport`, `importStatus`, `exportEntity` |
| `searchService` | `/search` | `search(query, types?, limit?)` |
| `userService` | `/users` | CRUD + `updateRole`, `activate` |
| `settingsService` | `/settings` | `get`, `update`, `uploadAvatar`, `deleteAvatar`, `changePassword` |
| `customFieldService` | `/custom-fields` | `entities`, `list`, `listCategory`, definition/group create/update/delete |
| `productService` | `/products` | CRUD + `deals`, `opportunities` — products carry `tax_id`/`tax` |
| `productCategoryService` | `/product-categories` | CRUD + `tree`, `reorder` |
| `opportunityProductService` | `/customers/{id}/opportunities/{id}/products` | `list`, `attach`, `update` (`quantity/unit_price/discount_percent/discount_amount/notes`), `detach` — lines returned in insertion order |
| `webhookService` | `/webhooks` | CRUD + `rotateSecret`, `deliveries`, `redeliver` |
| `noteService` | `/{entity}/{id}/notes`, `/notes` | `list`, `create`, `update`, `delete`, `togglePin` |
| `timelineService` | `/{entity}/{id}/timeline` | `list(entityType, entityId, filters)` |
| `auditLogService` | `/audit-logs` | `list`, `forEntity` |

---

## 10. Composables

### 10.1 useDataTable

Manages the full lifecycle of a paginated, sortable, filterable list.

```ts
function useDataTable<T>(
  fetchFn: (params: any) => Promise<PaginatedResponse<T>>,
  options?: { defaultSort?: string; defaultPerPage?: number; defaultFilters?: Record<string, any> }
) {
  // Returns
  return {
    rows: Ref<T[]>,
    loading: Ref<boolean>,
    total: Ref<number>,
    currentPage: Ref<number>,
    perPage: Ref<number>,
    sortBy: Ref<string>,
    sortDir: Ref<'asc' | 'desc'>,
    searchQuery: Ref<string>,
    filters: Ref<Record<string, any>>,
    fetch: () => Promise<void>,          // Call API with current params
    onSearch: (query: string) => void,   // Debounced, resets to page 1
    onFilter: (key: string, value: any) => void,
    onSort: (column: string) => void,    // Toggle direction
    onPageChange: (page: number) => void,
    onPerPageChange: (size: number) => void,
    refresh: () => Promise<void>,        // Re-fetch current page
  }
}
```

### 10.2 useCrudForm

Manages create/edit form state, submission, and server-side error mapping.

```ts
function useCrudForm<T>(options: {
  getService: (id: number) => Promise<T>              // For edit — fetch existing
  createService: (data: Partial<T>) => Promise<T>
  updateService: (id: number, data: Partial<T>) => Promise<T>
  redirectTo: RouteLocationRaw | ((item: T) => RouteLocationRaw)
  schema?: ZodType                                    // Client-side validation
}) {
  return {
    formData: Ref<Partial<T>>,
    errors: Ref<Record<string, string[]>>,
    loading: Ref<boolean>,
    isEdit: ComputedRef<boolean>,
    loadItem: (id: number) => Promise<void>,          // Populate formData for editing
    submit: () => Promise<void>,                      // Validate + call create or update
    setField: (key: string, value: any) => void,
    resetForm: () => void,
  }
}
```

### 10.3 usePermission

```ts
function usePermission() {
  const auth = useAuthStore()
  return {
    can: (permission: string) => auth.hasPermission(permission),
    canAny: (...permissions: string[]) => permissions.some(p => auth.hasPermission(p)),
    isAdmin: computed(() => auth.isAdmin),
    isManager: computed(() => auth.isManager),
  }
}
```

### 10.4 useConfirmDialog

```ts
function useConfirmDialog() {
  return {
    show: Ref<boolean>,
    message: Ref<string>,
    confirm: (msg: string) => Promise<boolean>,   // Opens dialog, resolves on user choice
    cancel: () => void,
  }
}
```

### 10.5 useQuickAdd

```ts
function useQuickAdd(submitFn: (data: any) => Promise<any>) {
  return {
    showModal: Ref<boolean>,
    open: () => void,
    close: () => void,
    onCreated: (callback: (newItem: any) => void) => void,
  }
}
```

### 10.6 useLocale

```ts
function useLocale() {
  return {
    currentLocale: ComputedRef<'en' | 'ar'>,
    isRTL: ComputedRef<boolean>,
    switchLocale: (locale: 'en' | 'ar') => void,
  }
}
```

### 10.7 useToast

```ts
function useToast() {
  return {
    success: (message: string) => void,
    error: (message: string) => void,
    warning: (message: string) => void,
    info: (message: string) => void,
  }
}
```

### 10.8 useAsyncReport

Manages dispatching an async backend job (report generation or export), polling its status, and notifying on completion.

```ts
function useAsyncReport() {
  return {
    asyncJob: Ref<AsyncJob | null>,       // Current job being polled
    polling: Ref<boolean>,                 // Whether polling is active
    startAsync: (
      dispatchFn: () => Promise<AsyncJob>, // Calls the POST endpoint that queues the job
      onComplete?: (job: AsyncJob) => void // Callback when job status becomes 'completed'
    ) => Promise<void>,
    stopPolling: () => void,               // Cancel polling (also auto-called on unmount)
  }
}
```

**Behaviour:**
- Calls `dispatchFn()` to get the initial `AsyncJob` (`status: 'pending'`).
- Starts `setInterval(3000)` polling `asyncJobService.get(id)`.
- On `completed`: stops polling, shows success toast, invokes `onComplete` callback.
- On `failed`: stops polling, shows error toast with `job.error` message.
- Auto-cleans up on component unmount via `onUnmounted`.
- Each report view uses **two** instances: one for async data generation, one for async file export.

### 10.9 useProductLabels

Company-configurable "Product" terminology (Company Settings → Terminology). Fetches settings once via the `companySettings` store; falls back to `products.singular` / `products.title` when unset.

```ts
function useProductLabels() {
  return {
    singular: ComputedRef<string>,               // e.g. "Service" / "خدمة"
    plural: ComputedRef<string>,                 // e.g. "Services" / "خدمات"
    hasCustom: ComputedRef<boolean>,            // Whether the active locale has a custom label
    productPerformanceTitle: ComputedRef<string>, // Label-aware report title (reports.productPerformanceCustom)
  }
}
```

Used by: sidebar, product list/form, opportunity products tab, product-performance report, import/export entity labels, AI chat source labels, roles module header (`usePermissionLabel().moduleLabel('products')`).

### 10.10 useFeatures

Quotations/invoices on-off flags from Company Settings. Reads the `companySettings` store; defaults to `true` before settings load so modules never flash-hide.

```ts
function useFeatures() {
  return {
    showQuotations: ComputedRef<boolean>,
    showInvoices: ComputedRef<boolean>,
    showSalesDocuments: ComputedRef<boolean>,  // either enabled
  }
}
```

Used by: sidebar (Sales Documents entry), router guard, `OpportunityDetailView` (tabs/buttons/fetch skips), `SalesDocumentsView` (tab hiding + default-tab switch).

---

## 11. Feature Modules

### 11.1 Dashboard

**View:** `DashboardView.vue`

Fetches all data from `GET /dashboard` + `GET /dashboard/activity-feed` in parallel.

**Layout (2-column grid on lg+, single column on mobile):**

```
┌─────────────────────┬──────────────────────┐
│   PipelineChart     │   RevenueChart       │
│   (bar/funnel)      │   (line chart)       │
├─────────────────────┼──────────────────────┤
│   TaskSummaryCard   │   CustomerHealthCard │
├─────────────────────┼──────────────────────┤
│   TopPerformersCard │   CampaignSummary    │  ← admin/manager only
├─────────────────────┴──────────────────────┤
│   ActivityFeed (paginated list)            │
└────────────────────────────────────────────┘
```

**PipelineChart:** Horizontal bar chart with 7 status segments. Each bar is clickable — navigates to leads list filtered by that status.

**RevenueChart:** Line or bar chart showing monthly revenue trend from `revenue.by_month`. Includes period comparison indicator (`+20%` badge).

**TaskSummaryCard:** Shows overdue (red), due today (amber), upcoming (blue), in-progress counts. Each is clickable → `tasks.index` with appropriate filter.

**CustomerHealthCard:** Shows total customers by status, average health score as a gauge/progress bar, count needing follow-up.

**TopPerformersCard:** Top 5 table — user name, leads won, won value. Admin/Manager only.

**CampaignSummaryCard:** Active campaigns count, total emails sent. Admin/Manager only.

**ActivityFeed:** Chronological list of recent CRM actions (from `/dashboard/activity-feed`). Each item shows icon, description, user, timestamp. Items link to the relevant entity.

### 11.2 Leads

**LeadListView:**
- `BaseDataTable` with columns: Name (`full_name`), Email, Organization, Status (`StatusBadge`), Source, Score (`ScoreBadge`), Assigned To, Created At.
- Filters: status (dropdown), source (dropdown), assigned_to (user dropdown), organization, tag, score_min/score_max.
- Row actions: View, Edit, Delete.
- Header actions: "New Lead" button.
- Selectable rows for bulk tag assignment.

**LeadDetailView:**
- Header: full name, status badge, score badge, assigned user.
- Tabs:
  1. **Overview** — key fields in `BaseCard` sections (Contact Info, Organization, Pipeline).
  2. **Activities** — `BaseDataTable` of lead activities with inline "Log Activity" form.
  3. **Timeline** — `TimelineView` component (fetches from `/leads/{id}/timeline`).
  4. **Notes** — `NoteThread` component.
  5. **Attachments** — `AttachmentList` with upload button.
  6. **Tags** — `TagList` with edit capability.
  7. **Audit Log** — `AuditLogTable` (if user has permission).
- Sidebar actions: Change Status (dropdown respecting transitions), Assign, Convert to Customer (visible when status is `negotiation`).

**LeadFormView:**
- `DynamicForm` with schema derived from backend validation rules (`04-validation-rules.md`).
- `organization_id` field has Quick-Add for Organization.
- `assigned_to` field is a searchable user select.
- `source` field is a dropdown with values from `Lead::SOURCES`.
- `status` field shown only on edit, respecting allowed transitions.
- Custom fields appended from `customFieldStore`.

### 11.3 Organizations

**OrganizationListView:**
- Columns: Name, Industry, Email, Phone, Leads Count, Contacts Count, Created At.
- Filters: search, industry, tag.

**OrganizationDetailView:**
- Header with name, industry badge.
- Tabs: Overview, Contacts (`BaseDataTable` + inline add contact form), Leads (filtered list), Notes, Attachments, Tags, Timeline, Audit Log.

**OrganizationFormView:**
- `DynamicForm` with address fields grouped in a `BaseCard` titled "Address".

### 11.4 Customers

**CustomerListView:**
- Columns: Company Name, Contact Name, Status, Health Score (`HealthScoreBar`), Account Manager, Contract Value, Next Follow-Up, Created At.
- Filters: status, health_below, account_manager_id, organization_id, follow_up_due, tag.

**CustomerDetailView:**
- Header: company name, status badge, health score gauge, and a WhatsApp action that opens the shared editable message modal.
- Tabs: Overview (contract info, origin lead link), Deals (`BaseDataTable` + "New Deal" button), Activities, Timeline, Notes, Attachments, Tags, Audit Log.
- The **Attachments** tab embeds `AttachmentList` (`entity-type="customers"`, upload gated by `create-attachments`, tab by `view-attachments`).

The opportunity detail's quotation and invoice views also expose WhatsApp sharing through the same modal. Document messages include a signed PDF link; the user can edit both the recipient phone number and message before initiating the send.

**CustomerFormView (edit only):**
- `DynamicForm` — contract dates, status, account manager, health score, notes.

### 11.5 Deals

**DealListView** — shown as a tab within CustomerDetailView.
- Columns: Title, Type, Value, Currency, Status, Start Date, End Date.
- "New Deal" opens `DealFormView` or inline modal.

**DealFormView:**
- `DynamicForm` — title, type (dropdown), value, currency, dates, notes.
- **Deal Items section:** table of line items with product selector (Quick-Add for Product), quantity, unit price, discount, computed subtotal. Add/remove rows dynamically.

### 11.6 Tasks

**TaskListView:**
- Columns: Title, Priority (colour-coded badge), Status, Due Date, Assignee, Linked Entity, Created At.
- Filters: status, priority, user_id, entity_type, overdue, due_today.
- My Summary panel at top (overdue, due today, upcoming counts).

**TaskFormView:**
- `DynamicForm` — title, description, priority, due date, assignee (user select), entity link (entity type + searchable entity select).

### 11.6a Activities

**ActivityListView** (`activities.index`, sidebar CRM section, `view-customers`):
- Columns: Subject, Type, Customer (link), Owner, Due Date, Status, Reminder.
- Filters: type, status (`upcoming|due_today|overdue|completed`).
- Row actions: complete, open customer, delete (permission-gated).

**ActivityFormModal** (shared, used by CustomerDetailView):
- Section 1 Interaction: type picker, channel, subject, notes.
- Section 2 Next Step: follow-up datetime (required for `follow_up`), optional reminder datetime (must be ≤ due date).
- Client validation blocks save when the reminder is past the due date or in the past (`reminderInPast`, enforced in `canSave`, not just the input `min` attribute); backend 422 errors map to per-field messages (`fieldErrors`) instead of a generic alert.
- CustomerDetailView shows a next-follow-up banner above the activity history with overdue highlighting.
- The same rules apply in **OpportunityActivityModal** (used by OpportunityDetailView).

**ActivityOutcomePicker** (shared, used by both detail views for completing activities):
- Split-button: main button completes the activity with the selected outcome, chevron opens the outcome menu.
- Outcomes are color-coded (`positive/success`, `neutral/secondary`, `negative/danger`, `escalated/warning`) with dot + icon; the main button tints to the selection.
- Completing **requires** an outcome — the action button stays disabled with a "Select outcome" label until one is picked. (The API still accepts a null outcome; the requirement is UI-enforced.)
- The menu teleports to `body` (`position: fixed`, `z-index: 2000`, flips upward near the viewport bottom, RTL-aware) so card `overflow: hidden` never clips it; closes on outside click, Escape, scroll, or resize.

**Detail-view reactivity:** after activity save/complete/delete, note add/pin/delete, or attachment upload/delete, the views reload the timeline tab when already visited (`refreshTimeline`) and CustomerDetailView additionally refreshes the customer header (health score, `last_contacted_at`). `AttachmentList` emits `uploaded`/`deleted` for this.

### 11.7 Reports

Each report view contains:
- Date range filter (date_from, date_to).
- Entity-specific filters (user_id, organization_id, etc.).
- Charts rendered via `vue-chartjs`.
- Summary cards at top.
- Detail table below.
- Export format selector (XLSX / PDF) + sync export link.
- **"Generate in Background"** button — queues async report data generation via `POST /reports/*/async`, polls with `useAsyncReport`, and injects the result into the view on completion.
- **"Export in Background"** button — queues async file export via `POST /reports/*/export/async`, polls with a second `useAsyncReport` instance, and shows a download link (via `GET /async-jobs/{id}/download`) when the file is ready.

**Async Report Flow:**
1. User clicks "Generate in Background" → `POST /reports/*/async` → returns `AsyncJob` (status: pending)
2. Progress bar (info alert) shows `asyncJob.progress`% while polling every 3s
3. On completion → report `result` data injected into `rows` + `summary` refs → table updates

**Async Export Flow:**
1. User selects format (XLSX/PDF) → clicks "Export in Background" → `POST /reports/*/export/async` → returns `AsyncJob`
2. Progress bar (warning alert) shows export progress
3. On completion → green success alert with "Download File" link → `GET /async-jobs/{id}/download`

### 11.8 Campaigns

**CampaignListView:**
- Columns: Name, Template, Status, Recipients, Sent/Failed counts, Scheduled At, Sent At.

**CampaignDetailView:**
- Stats cards (total, sent, failed, pending).
- Recipients table.
- Actions: Send Now, Schedule, Cancel (based on current status).

**CampaignFormView:**
- Name, Template selection (Quick-Add), Segment Filters (customer filter builder).

### 11.9 Email Templates

**TemplateListView + TemplateFormView:**
- Rich text editor for body with placeholder insertion toolbar.
- Live preview panel.
- Extracted placeholders displayed as badges.

### 11.10 Tags

**TagListView:**
- Simple list with inline edit.
- Colour picker for tag `color`.
- Delete with confirmation.

### 11.11 Products

**ProductListView:**
- Columns: Name, SKU, Unit Price, Tax (`name (percent%)` or `—`), Category, Status, Created At.
- Filters: search, category, is_active, and filterable `cf_` model/`ccf_` category fields.
- Row click opens `products.show`; the row edit action stays on `products.edit`.

**ProductCategoryListView:**
- Tree view with drag-reorder.
- Inline create/edit.
- Inline category form includes the independent Product Fields editor; child categories do not inherit definitions.

**TaxListView** (`taxes.index`, `view-taxes`):
- Flat table (Name, Rate %, Status) with inline create/edit form and active toggle; delete asks confirm and surfaces the backend "in use" error.
- Linked from the Sales sidebar section (`taxes.title`, percent icon).

**ProductFormView:**
- `DynamicForm` schema plus a Tax select (active taxes only, `Name (percent%)` labels; the product's current tax is kept selectable even when inactive).

**ProductDetailView:**
- Tabs: Details | Deals | Opportunities.
- Details shows catalog/category/tax information and model/category custom fields.
- Deals and Opportunities are plain-text related lists with pivot quantity/pricing data; there are no linked deal rows, notes, timeline, or attachment tabs.

**Opportunity products tab** (`OpportunityDetailView`):
- Invoice-style line table in insertion order: product, unit price, quantity, **discount** (per-line `%`/amount toggle + value input), **tax** (product tax badge + computed line tax), net subtotal.
- Draft rows for bulk attach; footer shows live subtotal / discount / tax / total. Discount math mirrors the backend (`lineDiscountAmount`); numbers stay LTR-isolated (`dir="ltr"` on `%` suffixes) in RTL.
- Quotation/invoice preview modals show per-line discount (`amount (pct%)`) and tax (`name (pct%)`) columns; header tax row shows the summed amount without a bogus single percent.

### 11.12 Webhooks

**WebhookListView + WebhookFormView:**
- URL, event multi-select, active toggle.
- Delivery log table with re-deliver button.
- Rotate secret action.

### 11.14 Audit Logs

**AuditLogView:**
- `BaseDataTable` with filters: entity_type, action, user_id, date range.
- Expandable rows showing old/new value diffs.

### 11.15 Import / Export

**ImportExportView:**
- Import and export sections are shown independently according to `import-data` and `export-data` permissions; the route is available to users with either permission.
- Import: CSV upload, template download, progress and recent import history for leads, customers, opportunities, products, and product categories.
- Export: streamed CSV downloads for leads, customers, opportunities, deals, products, and product categories. Product category relationships use slugs; category exports are parent-first. Product exports include inactive products by default.

### 11.16 Users (Admin)

**UserListView:**
- Columns: Name, Email, Role, Active, Created At.
- Filters: search, role, is_active.

**UserFormView:**
- `DynamicForm` — name, email, password (create only), role dropdown, phone, timezone dropdown (full IANA list via `Intl.supportedValuesOf`, common-zones fallback; stored custom values stay selectable), locale, is_active toggle.

### 11.17 Settings (Self-Service)

**UserSettingsView:**
- Tabbed layout: Profile, Notifications, Security.
- Profile: name (read-only), email (read-only), phone, timezone, locale, date format, avatar upload.
- Notifications: toggles for each notification type.
- Security: change password form.

**CompanySettingsView** (`company-settings`, `view-company-settings` view / `update-company-settings` edit):
- Cards: Company Information, Address, Terminology, **Features**, AI Service Provider, Logo.
- The Features card holds the `quotations_enabled` / `invoices_enabled` switches (`companySettings.features*`); saving patches the Pinia store so nav/tabs update instantly. Fields default ON when settings are unloaded.

### 11.18 Sales Documents

**View:** `resources/js/views/opportunities/SalesDocumentsView.vue`

- Route `salesDocuments` (`/sales-documents`) is available to admins and users with either `view-quotations` or `view-invoices` **and** at least one module enabled (`showSalesDocuments`); otherwise the router guard redirects to `forbidden`.
- Shows only authorized **and enabled** tabs and defaults to quotations when available, otherwise invoices; switching to a disabled type is a no-op and a watcher re-homes the active tab when flags resolve.
- Columns: document number, customer display name, opportunity title, status, currency/total, and document date.
- Uses `useDataTable()` with local state; each tab calls the matching `listGlobal()` service method.
- Search is debounced and covers document number, customer, or opportunity title. Status and inclusive from/to date filters reset the active tab's query.
- Quotation dates use `valid_until`; invoice dates use `issued_at`. Status and total columns are sortable.
- Selecting a row opens its opportunity. Row actions stream the document PDF in an iframe preview modal or download it directly.
- Switching tabs clears filters and pagination so quotation and invoice query state cannot leak between document types.

---

## 12. Internationalization (i18n)

### Setup (`i18n/index.ts`)

```ts
import { createI18n } from 'vue-i18n'
import en from './locales/en.json'
import ar from './locales/ar.json'

export const i18n = createI18n({
  legacy: false,                    // Composition API mode
  locale: localStorage.getItem('locale') || 'en',
  fallbackLocale: 'en',
  messages: { en, ar },
})
```

### Translation File Structure

```json
// en.json (excerpt)
{
  "common": {
    "search": "Search…",
    "create": "Create",
    "edit": "Edit",
    "delete": "Delete",
    "save": "Save",
    "cancel": "Cancel",
    "confirm": "Confirm",
    "loading": "Loading…",
    "noData": "No data found",
    "actions": "Actions",
    "status": "Status",
    "createdAt": "Created At",
    "yes": "Yes",
    "no": "No",
    "export": "Export",
    "import": "Import",
    "upload": "Upload",
    "filter": "Filter",
    "clearFilters": "Clear Filters",
    "showing": "Showing {from} to {to} of {total}",
    "perPage": "Per page",
    "quickAdd": "Quick Add"
  },
  "nav": {
    "dashboard": "Dashboard",
    "leads": "Leads",
    "customers": "Customers",
    "organizations": "Organizations",
    "deals": "Deals",
    "tasks": "Tasks",
    "reports": "Reports",
    "campaigns": "Campaigns",
    "emailTemplates": "Email Templates",
    "tags": "Tags",
    "products": "Products",
    "salesDocuments": "Sales Documents",
    "webhooks": "Webhooks",
    "auditLogs": "Audit Logs",
    "importExport": "Import / Export",
    "users": "Users",
    "settings": "Settings"
  },
  "auth": {
    "login": "Login",
    "email": "Email Address",
    "password": "Password",
    "confirmPassword": "Confirm Password",
    "rememberMe": "Remember me",
    "forgotPassword": "Forgot password?",
    "forgotPasswordTitle": "Reset your password",
    "forgotPasswordHelp": "Enter your email and we will send you a one-time code.",
    "sendCode": "Send code",
    "otp": "One-time code",
    "newPassword": "New password",
    "resetPassword": "Reset password",
    "backToLogin": "Back to login",
    "logout": "Logout",
    "name": "Full Name"
  },
  "leads": {
    "title": "Leads",
    "newLead": "New Lead",
    "firstName": "First Name",
    "lastName": "Last Name",
    "email": "Email",
    "phone": "Phone",
    "jobTitle": "Job Title",
    "source": "Source",
    "status": "Status",
    "estimatedValue": "Estimated Value",
    "expectedCloseDate": "Expected Close Date",
    "description": "Description",
    "organization": "Organization",
    "assignedTo": "Assigned To",
    "score": "Score",
    "activities": "Activities",
    "timeline": "Timeline",
    "convertToCustomer": "Won — Convert to Customer",
    "statusTransitions": {
      "new": "New",
      "contacted": "Contacted",
      "qualified": "Qualified",
      "proposal": "Proposal",
      "negotiation": "Negotiation",
      "won": "Won",
      "lost": "Lost"
    },
    "sources": {
      "web": "Web",
      "referral": "Referral",
      "cold_call": "Cold Call",
      "advertisement": "Advertisement",
      "social_media": "Social Media",
      "trade_show": "Trade Show",
      "other": "Other"
    }
  },
  "organizations": { "...": "..." },
  "customers": { "...": "..." },
  "deals": { "...": "..." },
  "tasks": { "...": "..." },
  "dashboard": { "...": "..." },
  "reports": { "...": "..." },
  "notifications": {
    "title": "Notifications",
    "markAllRead": "Mark all as read",
    "noNotifications": "No notifications",
    "viewAll": "View all"
  }
}
```

Arabic translations follow the same structure with RTL-appropriate text.

Later additions (mirrored in `ar.json`): `common.upload` ("Upload"/"رفع", used by `FormFileUpload` — never the data-import term), `products.tax` / `products.discountPercent`, `taxes.title` / `taxes.percent`, `companySettings.featuresTitle/featuresDesc/quotationsEnabled/invoicesEnabled`, and `roles.permissionLabels` + `roles.moduleLabels` entries for `create/update/delete/view-taxes`.

### Usage in Components

```vue
<template>
  <BaseButton icon="bi-plus" @click="create">{{ t('common.create') }}</BaseButton>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
const { t } = useI18n()
</script>
```

---

## 13. RTL / LTR Support

### Strategy

Bootstrap 5 is loaded by `resources/js/main.ts`. RTL behavior is handled by the document direction and the project's custom styles; do not dynamically swap a separate Bootstrap stylesheet at runtime.

### Implementation

1. **`main.ts`** — On boot, check stored locale:
   ```ts
   const locale = localStorage.getItem('locale') || 'en'
   document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr'
   document.documentElement.lang = locale
   ```

2. **Locale switching** — on switch:
   ```ts
   function switchLocale(newLocale: 'en' | 'ar') {
     i18n.global.locale.value = newLocale
     localStorage.setItem('locale', newLocale)
     document.documentElement.dir = newLocale === 'ar' ? 'rtl' : 'ltr'
     document.documentElement.lang = newLocale
   }
   ```

3. **`rtl.css`** — custom overrides:
   ```css
   [dir="rtl"] {
     // Sidebar flip
     .sidebar {
       right: 0;
       left: auto;
       border-right: none;
       border-left: 1px solid var(--bs-border-color);
     }

     // Icon positions
     .btn .bi {
       margin-right: 0;
       margin-left: 0.5rem;
     }

     // Table text alignment
     .table th,
     .table td {
       text-align: right;
     }

     // Breadcrumb separator
     .breadcrumb-item + .breadcrumb-item::before {
       content: "\\";
     }
   }
   ```

4. **CSS logical properties** — prefer `margin-inline-start` / `padding-inline-end` over `margin-left`/`padding-right` in all custom CSS. This ensures automatic RTL flipping.

5. **All icons** — use Bootstrap Icons which are direction-agnostic. For directional icons (arrows), use the `bi-arrow-right` class and flip via CSS `[dir="rtl"] .flip-icon { transform: scaleX(-1) }`.

6. **No Bootstrap `input-group` for joined controls** — the LTR-only bundle's physical radius/margins break under `dir="rtl"`. Use plain `d-flex gap-1` wrappers (see the opportunity discount `%` toggle, `.discount-toggle` in `OpportunityDetailView.vue`).

7. **Bidi isolation for `%`/parentheses** — ASCII `(10%)` inside RTL text mirrors to `)10%)(`. Wrap numeric suffixes in `dir="ltr"` spans (discount/tax badges, document preview cells).

---

## 14. Authentication & Authorization

### Token Management

- Authentication uses the Laravel session cookie. Do not store or attach a bearer token from frontend storage.
- On app boot, `authStore.initFromStorage()` calls `GET /auth/me` to validate the session and fetch user data including roles and permissions.
- On 401 response, clear token and redirect to login.

### Frontend Permission Checks

Permissions from the backend user object (via spatie/laravel-permission) are stored in `authStore.permissions`.

**Template-level hiding:**

```vue
<BaseButton v-if="can('update-customers')" @click="editCustomer">Edit</BaseButton>
```

**Route-level guarding:**

Single-permission routes use `meta.permission`. Routes that allow access with any one of several permissions use `meta.permissions`; the guard grants access when at least one listed permission is present. The Sales Documents route uses `['view-quotations', 'view-invoices']`. See Section 4.

**Permission constants (`utils/permissions.ts`):**

```ts
export const PERMISSIONS = {
  CREATE_USERS: 'create-users',
  UPDATE_USERS: 'update-users',
  VIEW_USERS: 'view-users',
  CREATE_CUSTOMERS: 'create-customers',
  UPDATE_CUSTOMERS: 'update-customers',
  VIEW_CUSTOMERS: 'view-customers',
  CREATE_DEALS: 'create-deals',
  UPDATE_DEALS: 'update-deals',
  VIEW_DEALS: 'view-deals',
  CREATE_TASKS: 'create-tasks',
  UPDATE_TASKS: 'update-tasks',
  VIEW_TASKS: 'view-tasks',
  CREATE_TAGS: 'create-tags',
  VIEW_TAGS: 'view-tags',
  CREATE_TEMPLATES: 'create-templates',
  UPDATE_TEMPLATES: 'update-templates',
  CREATE_CAMPAIGNS: 'create-campaigns',
  UPDATE_CAMPAIGNS: 'update-campaigns',
  VIEW_CAMPAIGNS: 'view-campaigns',
  VIEW_AUDIT_LOG: 'view-audit-log',
  VIEW_REPORTS: 'view-reports',
  CREATE_AI_CHATS: 'create-ai-chats',
  IMPORT_DATA: 'import-data',
  EXPORT_DATA: 'export-data',
  CREATE_CUSTOM_FIELDS: 'create-custom-fields',
  UPDATE_CUSTOM_FIELDS: 'update-custom-fields',
  DELETE_CUSTOM_FIELDS: 'delete-custom-fields',
  CREATE_PRODUCTS: 'create-products',
  UPDATE_PRODUCTS: 'update-products',
  VIEW_PRODUCTS: 'view-products',
  CREATE_WEBHOOKS: 'create-webhooks',
  UPDATE_WEBHOOKS: 'update-webhooks',
  VIEW_WEBHOOKS: 'view-webhooks',
} as const
```

---

## 15. Notifications (Real-Time)

### Polling Strategy

The `notificationStore` polls `GET /notifications/unread-count` every 60 seconds while the app is active.

```ts
// In App.vue or AppLayout.vue
onMounted(() => {
  notificationStore.startPolling(60_000)
})
onUnmounted(() => {
  notificationStore.stopPolling()
})
```

### NotificationDropdown

- Bell icon in the topbar with unread count badge.
- Click opens dropdown showing the 10 most recent notifications.
- Each notification item: icon (based on `entity_type`), title, body, relative timestamp.
- Click on a notification: mark as read + navigate to `action_url`.
- "Mark all as read" link.
- "View all" link → full notifications page (if implemented) or modal.

### Future Enhancement

Replace polling with Laravel Echo + Pusher/WebSocket for real-time push. The store interface stays the same; only the transport changes.

---

## 16. Error Handling

### API Error Flow

```
Service call → Axios interceptor catches error
  ├── 401 → logout + redirect to /login
  ├── 403 → toast "You don't have permission" + redirect to /forbidden
  ├── 404 → toast "Resource not found" + redirect to previous page
  ├── 422 → return error.response.data.errors to calling composable
  │          → useCrudForm maps errors to form fields
  ├── 429 → toast "Too many requests, please wait"
  └── 500 → toast "Server error, please try again"
```

### Form Validation

1. **Client-side:** Zod schemas in `useCrudForm` validate before submission.
2. **Server-side:** 422 errors map `{ field: [messages] }` to `errors` ref displayed below each `FormField`.

### Empty States

Every list view uses `BaseEmptyState` when no data is returned — icon, message, and optional action button.

### Loading States

- `BaseDataTable` shows a semi-transparent overlay with `BaseSpinner`.
- `BaseCard` can show a skeleton loader.
- `BaseButton` with `loading` prop shows inline spinner and disables interaction.

---

## 17. Implementation Phases

See **[implementation-phases.md](implementation-phases.md)** for the full checklist of 10 phases with trackable items.

---

## Appendix A: TypeScript Type Definitions

### Core Entity Models

```ts
// types/models.ts

interface User {
  id: number
  name: string
  email: string
  phone?: string
  timezone: string
  locale: 'en' | 'ar'
  role: 'admin' | 'manager' | 'sales_rep'
  is_active: boolean
  avatar_url?: string
  permissions: string[]
  created_at: string
}

interface Organization {
  id: number
  name: string
  industry?: string
  website?: string
  email?: string
  phone?: string
  address: {
    line_1?: string
    line_2?: string
    city?: string
    state?: string
    postal_code?: string
    country?: string
  }
  notes?: string
  leads_count?: number
  contacts_count?: number
  created_by: { id: number; name: string }
  tags?: Tag[]
  custom_fields?: Record<string, any>
  created_at: string
  updated_at: string
}

interface Lead {
  id: number
  first_name: string
  last_name: string
  full_name: string
  email?: string
  phone?: string
  title?: string
  source?: LeadSource
  status: LeadStatus
  estimated_value?: string
  expected_close_date?: string
  description?: string
  organization?: { id: number; name: string }
  assigned_to?: { id: number; name: string }
  created_by: { id: number; name: string }
  activities_count?: number
  score?: number
  score_breakdown?: Record<string, number>
  scored_at?: string
  tags?: Tag[]
  custom_fields?: Record<string, any>
  created_at: string
  updated_at: string
}

interface Customer {
  id: number
  company_name: string
  contact_name: string
  contact_email?: string
  contact_phone?: string
  status: CustomerStatus
  contract_value?: string
  notes?: string
  converted_at: string
  lead?: { id: number; full_name: string; source?: string }
  organization?: { id: number; name: string }
  account_manager?: { id: number; name: string }
  health_score: number
  last_contacted_at?: string
  next_follow_up_at?: string
  activities_count?: number
  opportunities_count?: number
  tags?: Tag[]
  custom_fields?: Record<string, any>
  created_at: string
  updated_at: string
}

interface Contact {
  id: number
  first_name: string
  last_name: string
  full_name: string
  email?: string
  phone?: string
  title?: string
  is_primary: boolean
  created_at: string
  updated_at: string
}

interface OpportunityContractFields {
  id: number
  title: string
  type: 'initial' | 'upsell' | 'cross_sell' | 'renewal' | 'downgrade'
  amount: string
  currency: string
  stage: OpportunityStage
  start_date?: string
  end_date?: string
  notes?: string
  created_by: { id: number; name: string }
  created_at: string
  updated_at: string
}

interface OpportunityProduct {
  id: number
  product: { id: number; name: string; sku?: string }
  quantity: number
  unit_price?: string
  notes?: string
}

interface DocumentCustomerSummary {
  id: number
  display_name: string
}

interface DocumentOpportunitySummary {
  id: number
  title: string
}

interface QuotationListItem {
  document_type: 'quotation'
  id: number
  quotation_number: string
  status: 'draft' | 'sent' | 'accepted' | 'rejected' | 'expired'
  currency: string
  total: string
  valid_until: string
  sent_at?: string | null
  customer_id: number
  opportunity_id: number
  customer?: DocumentCustomerSummary
  opportunity?: DocumentOpportunitySummary
  created_at: string
}

interface InvoiceListItem {
  document_type: 'invoice'
  id: number
  invoice_number: string
  status: 'issued' | 'paid' | 'cancelled'
  currency: string
  total: string
  issued_at: string
  customer_id: number
  opportunity_id: number
  quotation_id?: number | null
  customer?: DocumentCustomerSummary
  opportunity?: DocumentOpportunitySummary
  created_at: string
}

type SalesDocument = QuotationListItem | InvoiceListItem

interface Task {
  id: number
  title: string
  description?: string
  priority: TaskPriority
  status: TaskStatus
  due_date?: string
  completed_at?: string
  is_overdue: boolean
  assignee: { id: number; name: string }
  created_by: { id: number; name: string }
  entity?: { type: string; id: number; name: string }
  created_at: string
  updated_at: string
}

interface Tag {
  id: number
  name: string
  color?: string
  created_at: string
}

interface Notification {
  id: string
  type: string
  data: {
    title: string
    body: string
    action_url: string
    entity_type?: string
    entity_id?: number
    [key: string]: any
  }
  read_at: string | null
  created_at: string
}

interface LeadActivity {
  id: number
  type: ActivityType
  subject: string
  description?: string
  due_date?: string
  completed_at?: string
  is_completed: boolean
  user: { id: number; name: string }
  created_at: string
  updated_at: string
}

interface CustomerActivity {
  id: number
  type: CustomerActivityType
  channel?: ActivityChannel
  subject: string
  description?: string
  outcome?: ActivityOutcome
  due_date?: string
  reminder_at?: string
  reminder_sent_at?: string
  completed_at?: string
  is_completed: boolean
  is_overdue: boolean
  user: { id: number; name: string }
  customer?: { id: number; display_name: string }
  created_at: string
  updated_at: string
}

interface Attachment {
  id: number
  original_name: string
  mime_type: string
  size: number
  size_human: string
  download_url: string
  uploaded_by: { id: number; name: string }
  created_at: string
}

interface Note {
  id: number
  body: string
  is_pinned: boolean
  author: { id: number; name: string }
  parent_id: number | null
  replies_count: number
  replies?: Note[]
  created_at: string
  updated_at: string
}

interface TimelineEvent {
  id: string
  type: string
  subtype?: string
  title: string
  description?: string
  user: { id: number; name: string }
  metadata?: Record<string, any>
  timestamp: string
}

interface AuditLog {
  id: number
  user: { id: number; name: string } | null
  entity_type: string
  entity_id: number
  action: 'created' | 'updated' | 'deleted'
  changes: Record<string, { old: any; new: any }>
  ip_address?: string
  created_at: string
}

interface EmailTemplate {
  id: number
  name: string
  subject: string
  body: string
  placeholders: string[]
  created_at: string
}

interface Campaign {
  id: number
  name: string
  template: { id: number; name: string }
  status: CampaignStatus
  segment_filters?: Record<string, any>
  stats: { total_recipients: number; sent: number; failed: number; pending: number }
  scheduled_at?: string
  sent_at?: string
  created_at: string
}

interface Product {
  id: number
  name: string
  sku?: string
  description?: string
  unit_price: string
  currency: string
  category?: { id: number; name: string; slug: string }
  is_active: boolean
  created_at: string
}

interface ProductCategory {
  id: number
  name: string
  slug: string
  parent_id: number | null
  description?: string
  sort_order: number
  children?: ProductCategory[]
}

interface WebhookEndpoint {
  id: number
  url: string
  events: string[]
  is_active: boolean
  description?: string
  last_triggered_at?: string
  failure_count: number
  created_at: string
}

interface CustomFieldDefinition {
  id: number
  group_id: number
  entity_type: string
  scope: 'entity' | 'category'
  category_id: number | null
  name: string
  label: string
  type: 'text' | 'textarea' | 'number' | 'decimal' | 'date' | 'datetime'
      | 'select' | 'multi_select' | 'boolean' | 'color' | 'url' | 'email' | 'phone'
  options?: string[]
  is_required: boolean
  is_filterable: boolean
  sort_order: number
  created_at: string
}

interface CustomFieldGroup {
  id: number
  entity_type: string
  scope: 'entity' | 'category'
  category_id: number | null
  scope_key: string
  name: string
  description: string | null
}

interface Import {
  id: number
  entity_type: string
  original_filename: string
  status: 'pending' | 'processing' | 'completed' | 'failed'
  total_rows?: number
  processed_rows: number
  successful_rows: number
  failed_rows: number
  errors?: { row: number; field: string; message: string }[]
  completed_at?: string
  created_at: string
}
```

### Enums

```ts
// types/enums.ts

type LeadStatus = 'new' | 'contacted' | 'qualified' | 'proposal' | 'negotiation' | 'won' | 'lost'
type LeadSource = 'web' | 'referral' | 'cold_call' | 'advertisement' | 'social_media' | 'trade_show' | 'other'
type CustomerStatus = 'active' | 'at_risk' | 'churned' | 'paused' | 'renewed'
type DealType = 'initial' | 'upsell' | 'cross_sell' | 'renewal' | 'downgrade'
type DealStatus = 'active' | 'completed' | 'cancelled' | 'pending'
type TaskPriority = 'low' | 'medium' | 'high' | 'urgent'
type TaskStatus = 'pending' | 'in_progress' | 'completed' | 'cancelled'
type ActivityType = 'call' | 'email' | 'meeting' | 'task' | 'note'
type CustomerActivityType = 'onboarding' | 'support' | 'check_in' | 'review' | 'upsell' | 'renewal' | 'training' | 'complaint' | 'follow_up' | 'whatsapp' | 'general'
type ActivityChannel = 'call' | 'email' | 'meeting' | 'whatsapp' | 'in_person' | 'other'
type ActivityOutcome = 'positive' | 'neutral' | 'negative' | 'escalated'
type CampaignStatus = 'draft' | 'scheduled' | 'sending' | 'sent' | 'cancelled'
```

### API Response Wrappers

```ts
// types/api.ts

interface ApiResponse<T> {
  data: T
}

interface PaginatedResponse<T> {
  data: T[]
  links: {
    first: string
    last: string
    prev: string | null
    next: string | null
  }
  meta: {
    current_page: number
    from: number
    last_page: number
    per_page: number
    to: number
    total: number
  }
}

interface PaginationParams {
  page?: number
  per_page?: number
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  search?: string
}
```

---

## Appendix B: Status Color Map

```ts
// utils/constants.ts

export const LEAD_STATUS_COLORS: Record<LeadStatus, string> = {
  new:          'primary',
  contacted:    'info',
  qualified:    'warning',
  proposal:     'secondary',
  negotiation:  'dark',
  won:          'success',
  lost:         'danger',
}

export const CUSTOMER_STATUS_COLORS: Record<CustomerStatus, string> = {
  active:   'success',
  at_risk:  'warning',
  churned:  'danger',
  paused:   'secondary',
  renewed:  'info',
}

export const TASK_PRIORITY_COLORS: Record<TaskPriority, string> = {
  low:    'secondary',
  medium: 'info',
  high:   'warning',
  urgent: 'danger',
}

export const DEAL_STATUS_COLORS: Record<DealStatus, string> = {
  active:    'success',
  completed: 'info',
  cancelled: 'danger',
  pending:   'warning',
}

export const LEAD_STATUS_TRANSITIONS: Record<LeadStatus, LeadStatus[]> = {
  new:          ['contacted'],
  contacted:    ['qualified', 'lost'],
  qualified:    ['proposal', 'lost'],
  proposal:     ['negotiation', 'lost'],
  negotiation:  ['won', 'lost'],
  won:          [],
  lost:         ['new'],
}
```

---

## Appendix C: Environment Variables

```env
# .env (Vite)
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
VITE_APP_NAME=CRM
VITE_DEFAULT_LOCALE=en
VITE_NOTIFICATION_POLL_INTERVAL=60000
```

---

## Appendix D: Vite Configuration

```ts
// vite.config.ts
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
})
```
