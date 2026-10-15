# BeTopCRM API Specification

A spec-driven Laravel 13 CRM application with a Vue 3 frontend covering **Customers (unified: individual|company, is_lead flag for leads vs customers, JSON contacts/addresses)**, **Opportunities (including contract terms and product line items)**, **Quotations and Invoices**, **Customer Activities**, **Tasks**, **Tags**, **Email Campaigns**, **Notifications**, **Audit Logging**, **File Attachments**, **Import/Export**, **Global Search**, **Dashboard**, **User Management**, **Custom Fields**, **Lead Scoring (on lead-customers)**, **Products Catalog**, **Webhooks**, **Notes/Comments**, **Activity Timeline**, **Async Jobs (Polling)**, **Company Settings**, **AI Features**, and **outbound WhatsApp messaging**.

Deal functionality has been consolidated into Opportunities. The former `deals` and `deal_items` tables are dropped by the consolidation migration; current API, UI, reports, and contract expiry alerts use Opportunities. Customer `contract_value` is a derived summary of won Opportunity amounts, while contract type and dates live on Opportunities. The `{{deal_value}}` email-template variable remains as a compatibility placeholder.

## Documents

| File | Description |
|---|---|
| [01-domain-model.md](01-domain-model.md) | Database schema, tables, columns, and relationships |
| [02-api-endpoints.md](02-api-endpoints.md) | Every REST endpoint with method, URI, description |
| [03-models.md](03-models.md) | Eloquent models, relationships, casts, scopes |
| [04-validation-rules.md](04-validation-rules.md) | Form Request classes and their rules |
| [05-resources.md](05-resources.md) | API Resource response shapes |
| [06-services.md](06-services.md) | Service layer contracts and business logic |
| [07-events.md](07-events.md) | Domain events and listeners |
| [08-file-structure.md](08-file-structure.md) | Full list of files to create |
| [09-implementation-phases.md](09-implementation-phases.md) | Ordered phases for building the feature |
| [10-roles-permissions.md](10-roles-permissions.md) | Roles, permissions, authorization policies, and custom role management |
| [11-reports.md](11-reports.md) | Report endpoints for user performance, company, and customer analytics |
| [12-notifications.md](12-notifications.md) | In-app notifications and scheduled reminders |
| [13-tasks.md](13-tasks.md) | General-purpose task/to-do system |
| [14-tags.md](14-tags.md) | Polymorphic tagging for leads, customers, organizations |
| [15-email-campaigns.md](15-email-campaigns.md) | Email templates and bulk campaign sending |
| [16-audit-log.md](16-audit-log.md) | Automatic change tracking across entities |
| [17-file-attachments.md](17-file-attachments.md) | Polymorphic file attachments for documents |
| [18-import-export.md](18-import-export.md) | Queued CSV imports and streamed CSV exports for customers and the product catalog |
| [19-global-search.md](19-global-search.md) | Unified search across all entity types |
| [20-enhanced-dashboard.md](20-enhanced-dashboard.md) | Comprehensive dashboard with pipeline, revenue, tasks, activity feed |
| [21-user-settings.md](21-user-settings.md) | User settings, notification preferences, and admin user management |
| [22-custom-fields.md](22-custom-fields.md) | Admin-defined custom fields per entity type (JSON storage) |
| [23-lead-scoring.md](23-lead-scoring.md) | Automated lead scoring with configurable weights |
| [24-products-catalog.md](24-products-catalog.md) | Products/services catalog with Opportunity line items |
| [25-webhooks.md](25-webhooks.md) | Event-driven HTTP callbacks for external integrations |
| [27-notes-comments.md](27-notes-comments.md) | Polymorphic threaded notes with @mentions |
| [28-activity-timeline.md](28-activity-timeline.md) | Unified per-entity activity timeline |
| [29-lead-products.md](29-lead-products.md) | Linking catalog products to a lead as interest items |
| [30-company-settings.md](30-company-settings.md) | Company info, logo, address, and regional defaults |
| [31-ai-email-generator.md](31-ai-email-generator.md) | AI-assisted email template draft generation (Ollama / Gemini) |
| [32-whatsapp-messaging.md](32-whatsapp-messaging.md) | Outbound customer messages and sales-document sharing through WhatsApp |
| [34-ai-chat.md](34-ai-chat.md) | AI reporting & analytics chat over live CRM aggregates (stateless, async) |
| [frontend-spec.md](frontend-spec.md) | Vue 3 frontend architecture, routes, components, services, state, and UI conventions |

## Stack

- **PHP** 8.3+
- **Laravel** 13
- **Sanctum** 4 (token auth)
- **spatie/laravel-permission** 6 (roles & permissions)
- **laravel/ai** 0 (AI features — Ollama locally, Gemini in production)
- **Pest** 4 (testing)
- **MySQL** (database)
- **Frontend** Vue 3, Vite, TypeScript, Pinia, Vue Router, Bootstrap 5
