---
applyTo: "**"
---

# GitHub Copilot Instructions — BeTopCRM

This is a **spec-driven Laravel 13 CRM application with an integrated Vue frontend**. The full specification lives in `docs/spec/` (31 backend documents plus the frontend specification). Always consult the relevant spec before generating or modifying any code. Follow these rules strictly.

## 0. Universal Agent and Provider Rules

- These instructions apply to every AI agent, coding assistant, model, and provider working in this repository.
- Treat the repository code, tests, configuration, and specifications as the source of truth; do not assume provider-specific defaults.
- Before changing code, inspect the relevant existing files and documentation and follow the project’s established conventions.
- Preserve existing user changes and unrelated worktree changes.
- Make the smallest correct change, add or update tests for behavior changes, and verify the affected tests before finalizing.
- Do not introduce provider-specific SDKs, agent configuration, tools, or dependencies unless explicitly requested and documented for this project.
- If an agent or provider cannot access a required tool or document, state the limitation rather than inventing project behavior.

---

## 1. Project Overview

- **Stack:** PHP 8.3+, Laravel 13, Sanctum 4, spatie/laravel-permission v6, Pest 4, MySQL, Vue 3, Vite, TypeScript, Pinia, Bootstrap 5
- **i18n:** Supported locales are `en` (default) and `ar`. Each user has a `locale` column. The `SetLocale` middleware (`app/Http/Middleware/SetLocale.php`) runs in the `api` group, reads `$user->locale`, and calls `App::setLocale()`. Arabic lang files live in `lang/ar/`; custom messages in `lang/en/messages.php` and `lang/ar/messages.php`.
- **All API endpoints are prefixed with `/api/v1`** — register every route under this prefix
- **Auth:** Laravel session cookies (`withCredentials`). `/auth/login` is public; all other routes require `auth:sanctum`. Never persist or attach bearer tokens — there are none. New users are created through authenticated user management.
- **Queue driver** (Redis or database) is required for: webhooks (`DispatchWebhookJob`), imports (`ProcessImportJob`), email campaigns (`SendCampaignEmailJob`)
- Full spec index: [`docs/spec/README.md`](../docs/spec/README.md)
- Implementation phases: [`docs/spec/09-implementation-phases.md`](../docs/spec/09-implementation-phases.md)

---

## 2. Architecture — The Golden Rules

### Controllers are thin
- Controllers contain **no business logic**. They receive a request, call one service method, and return a resource.
- Every controller action must call `$this->authorize()` before touching the service.
- Never call `Gate::` directly inside a controller.
- Never call `DB::` or Eloquent directly inside a controller.

```php
// ✅ Correct
public function store(StoreLeadRequest $request): LeadResource
{
    $this->authorize('create', Lead::class);
    $lead = $this->leadService->create($request->validated(), $request->user());
    return new LeadResource($lead);
}

// ❌ Wrong — business logic in controller
public function store(StoreLeadRequest $request): LeadResource
{
    $lead = Lead::create([...$request->validated(), 'created_by' => $request->user()->id]);
    return new LeadResource($lead);
}
```

### Services own all business logic
- All services live in `app/Services/`; inject via constructor (`__construct(private LeadService $leadService) {}`)
- No repository layer — services use Eloquent models directly
- Standard service method signatures:
  - `list(array $filters): LengthAwarePaginator`
  - `create(array $data, User $user): Model`
  - `update(Model $model, array $data): Model`
  - `delete(Model $model): void`

### Filter pipeline
- Query filters live in `app/Filters/` as dedicated Filter classes (e.g., `OrganizationFilter`, `LeadFilter`)
- Services pass the base query through the filter pipeline: `(new OrganizationFilter($filters))->apply($query)`
- Never build conditional `where()` chains inside a service — use the Filter class

---

## 3. Models

- All models use the `HasFactory` trait
- Entity models (organizations, leads, customers, deals, contacts, tasks) must use `SoftDeletes`
- All decimal, boolean, date, and JSON columns must be listed in `$casts`
- Use constants on the model for all enum-like values:
  ```php
  const STATUSES = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost'];
  const SOURCES  = ['website', 'referral', 'cold_call', 'event', 'social_media', 'other'];
  const STATUS_TRANSITIONS = [
      'new'         => ['contacted'],
      'contacted'   => ['qualified', 'lost'],
      'qualified'   => ['proposal', 'lost'],
      'proposal'    => ['negotiation', 'lost'],
      'negotiation' => ['won', 'lost'],
  ];
  ```
- Add named scopes for all common query patterns (`scopeSearch`, `scopeActive`, `scopeByStatus`, etc.)

### Polymorphic traits — apply per model
| Trait | Apply to |
|---|---|
| `HasTags` (`app/Models/Traits/HasTags.php`) | Customer, Opportunity |
| `Auditable` (`app/Models/Traits/Auditable.php`) | Customer, CustomerActivity, Opportunity, OpportunityActivity, Task, Tag, EmailTemplate, Campaign |
| `HasAttachments` (`app/Models/Traits/HasAttachments.php`) | Customer, Opportunity |
| `HasCustomFields` (`app/Models/Traits/HasCustomFields.php`) | Customer, Opportunity, Product |
| `HasNotes` (`app/Models/Traits/HasNotes.php`) | Customer, Opportunity |

---

## 4. Authorization

- **Every** controller action calls `$this->authorize('ability', $model)` or `$this->authorize('ability', ModelClass::class)` — no exceptions
- Authorization delegates to Policies in `app/Policies/`; do not embed permission logic in controllers
- Routes use `permission:` middleware **only** for route **groups** (e.g., `middleware('permission:manage-reports')` on a route group), not individual route actions
- **Never** hardcode role checks (`hasRole('admin')`) in controllers or services — only use permission checks (`can('manage-leads')`)

### Roles (3 total)
| Role | Description |
|---|---|
| `admin` | Full access to everything |
| `manager` | Access to all records; cannot manage users or system config |
| `sales_rep` | Access only to their own/assigned records |

### `sales_rep` scope rules
- Leads: only own or assigned leads
- Customers: only own managed customers
- Deals: only deals on own customers
- Activities: only own activities

---

## 5. Validation — Form Requests

- All Form Requests live in `app/Http/Requests/{Entity}/` (entity-specific subdirectory)
- `authorize()` in Form Requests **delegates to policies**, not direct permission checks:
  ```php
  public function authorize(): bool
  {
      return $this->user()->can('create', Lead::class);
  }
  ```
- `Update*Request`: every field uses `sometimes` not `required`
- `created_by`, `user_id`, `assigned_to` are **never** in Form Request rules — always set server-side in services
- Unique constraints on Update requests must exclude the current record:
  ```php
  Rule::unique('leads', 'email')->ignore($this->lead)
  ```

---

## 6. API Resources

- **Every** controller response must go through an API Resource — never return raw models or `response()->json()`
- Resources live in `app/Http/Resources/{Entity}/`
- Collections use `ResourceCollection`
- Condensed related objects (e.g., `created_by`) as `{ "id": 1, "name": "John Doe" }` — **not** full nested resources
- Counts (e.g., `leads_count`, `contacts_count`) are included only when loaded via `withCount()`
- Address fields are grouped into a nested `address` object

---

## 7. Business Rules — Critical

### `created_by` is always server-side
```php
// ✅ In service
$data['created_by'] = $user->id;
$lead = Lead::create($data);

// ❌ Never from request input
```

### Status transitions — validate before persisting
```php
// In LeadService::updateStatus()
$allowed = Lead::STATUS_TRANSITIONS[$lead->status] ?? [];
if (!in_array($newStatus, $allowed)) {
    throw ValidationException::withMessages([
        'status' => "Cannot transition from {$lead->status} to {$newStatus}."
    ]);
}
```

- Valid Lead pipeline: `new → contacted → qualified → proposal → negotiation → lost` (leads never convert via a `won` status)
- When an opportunity is marked **`won`** and its customer `is_lead`, the service automatically converts the customer (`is_lead=false, status=active, converted_at=now()`)

### No N+1 queries in reports
- `ReportService` uses `DB::raw()` for aggregates
- Never loop over collections to aggregate data — use database-level aggregation

---

## 8. Events & Listeners

- Events live in `app/Events/`, Listeners in `app/Listeners/`
- Register bindings in `AppServiceProvider::boot()` using `Event::listen()`
- Defined events:
  - `LeadStatusChanged` — dispatched by `LeadService::updateStatus()`, carries `lead`, `oldStatus`, `newStatus`, `changedBy`
  - `LogLeadStatusChange` listener handles it

---

## 9. Audit Logging

- The `Auditable` trait auto-logs `created`, `updated`, `deleted` events to the `audit_logs` table
- The `audit_logs` table is **append-only** — never update or delete rows
- `AuditLog::record($model, 'action')` is called from model boot hooks inside the trait — never call it manually from controllers or services
- `ip_address` and `user_agent` are captured from the current request

---

## 10. Testing

- All tests use **Pest 4** syntax — no PHPUnit class-based tests
- Test database: `crm_test` (configured in `phpunit.xml`)
- Use `RefreshDatabase` — each test starts fresh, no shared state between tests
- Use Laravel factories for all test data
- Seed roles/permissions with `artisan db:seed --class=RolesAndPermissionsSeeder` in test setup
- Every feature has:
  - A happy-path test
  - An unauthorized-access test (assert 403 for wrong role)
  - A validation test (assert 422 for bad input)
- Policy/permission enforcement must be tested in dedicated `*AuthorizationTest` files

---

## 11. File & Directory Conventions

```
app/
  Http/
    Controllers/Api/      ← all API controllers
    Requests/{Entity}/    ← Store* and Update* form requests per entity
    Resources/{Entity}/    ← API Resources and Collections
  Models/
    Traits/               ← HasTags, Auditable, HasAttachments, HasCustomFields, HasNotes
  Services/               ← one service class per entity (e.g., LeadService.php)
  Filters/                ← one filter class per entity (e.g., LeadFilter.php)
  Policies/               ← one policy per entity (e.g., LeadPolicy.php)
  Events/                 ← domain events
  Listeners/              ← event listeners
  Jobs/                   ← queued jobs (DispatchWebhookJob, ProcessImportJob, SendCampaignEmailJob)
  Mail/                   ← Mailable classes for campaigns
```

---

## 12. Permissions Reference (69 total)

All `manage-*` permissions have been replaced with granular CRUD permissions. The `organizations`, `leads`, `contacts`, and `deals` groups are **not** registered — customers and opportunities are unified.

### Entity CRUD permissions
```
create-customers       update-customers       delete-customers       view-customers
create-opportunities   update-opportunities   delete-opportunities   view-opportunities
create-quotations                              view-quotations      send-quotations
create-invoices                                view-invoices
create-activities      update-activities      delete-activities
create-tasks           update-tasks           delete-tasks           view-tasks         complete-tasks
create-tags            update-tags            delete-tags            view-tags
create-campaigns       update-campaigns       delete-campaigns       view-campaigns     send-campaigns
create-attachments     update-attachments     delete-attachments     view-attachments
create-users           update-users           delete-users           view-users         view-assignable-users
create-custom-fields   update-custom-fields   delete-custom-fields
create-webhooks        update-webhooks        delete-webhooks        view-webhooks
create-products        update-products        delete-products        view-products
create-taxes           update-taxes           delete-taxes           view-taxes
create-templates       update-templates       delete-templates
                                                                    view-reports
                                                                    view-audit-log
                                                                    export-data      import-data
                                                                    view-company-settings  update-company-settings
                                                                    create-ai-chats
```

### Role permission matrix
| Permission group     | admin | manager | sales_rep |
|---|---|---|---|
| customers CRUD   | ✅ all | ✅ all | create + update + view |
| opportunities CRUD | ✅ all | ✅ all | create + update + view |
| quotations (create/view/send) | ✅ | ✅ | ✅ |
| invoices (create/view) | ✅ | ✅ | ✅ |
| activities CRUD  | ✅ all | ✅ all | create + update |
| tasks CRUD       | ✅ all | ✅ all | create + update + view (+ complete own) |
| tags CRUD        | ✅ all | ✅ all | view only |
| campaigns CRUD   | ✅ all | ✅ all | view only |
| attachments CRUD | ✅ all | ✅ all | create + update + view |
| users CRUD       | ✅ all | ❌ none | ❌ none |
| custom fields CRUD | ✅ all | ❌ none | ❌ none |
| webhooks CRUD    | ✅ all | ❌ none | ❌ none |
| products CRUD    | ✅ all | ✅ all | view only |
| taxes CRUD       | ✅ all | ✅ all | view only |
| templates CRUD   | ✅ all | ✅ all | ❌ none |
| view-reports     | ✅ | ✅ | ❌ |
| view-audit-log   | ✅ | ❌ | ❌ |
| send-campaigns   | ✅ | ✅ | ❌ |
| export-data      | ✅ | ✅ | ✅ |
| import-data      | ✅ | ✅ | ❌ |

---

## 13. Spec Files — Quick Reference

Before generating any code for a feature, read the corresponding spec(s):

| Feature | Spec file(s) |
|---|---|
| Database schema | `docs/spec/01-domain-model.md` |
| All API endpoints | `docs/spec/02-api-endpoints.md` |
| Eloquent models | `docs/spec/03-models.md` |
| Validation rules | `docs/spec/04-validation-rules.md` |
| API Resources | `docs/spec/05-resources.md` |
| Service business logic | `docs/spec/06-services.md` |
| Events & listeners | `docs/spec/07-events.md` |
| All files to create | `docs/spec/08-file-structure.md` |
| Build order / phases | `docs/spec/09-implementation-phases.md` |
| Roles, permissions, policies | `docs/spec/10-roles-permissions.md` |
| Reports | `docs/spec/11-reports.md` |
| Notifications | `docs/spec/12-notifications.md` |
| Tasks | `docs/spec/13-tasks.md` |
| Tags | `docs/spec/14-tags.md` |
| Email campaigns | `docs/spec/15-email-campaigns.md` |
| Audit log | `docs/spec/16-audit-log.md` |
| File attachments | `docs/spec/17-file-attachments.md` |
| Import / Export | `docs/spec/18-import-export.md` |
| Global search | `docs/spec/19-global-search.md` |
| Dashboard | `docs/spec/20-enhanced-dashboard.md` |
| User settings & management | `docs/spec/21-user-settings.md` |
| Custom fields | `docs/spec/22-custom-fields.md` |
| Lead scoring | `docs/spec/23-lead-scoring.md` |
| Products catalog | `docs/spec/24-products-catalog.md` |
| Webhooks | `docs/spec/25-webhooks.md` |
| Notes / Comments | `docs/spec/27-notes-comments.md` |
| Activity timeline | `docs/spec/28-activity-timeline.md` |
| Lead products | `docs/spec/29-lead-products.md` |
| Company settings | `docs/spec/30-company-settings.md` |
| AI email generator | `docs/spec/31-ai-email-generator.md` |
| Custom field groups | `docs/spec/32-custom-field-groups.md` |
| WhatsApp messaging | `docs/spec/32-whatsapp-messaging.md` |
| App reuse catalog | `docs/spec/33-app-reuse-catalog.md` |
| AI chat | `docs/spec/34-ai-chat.md` |
| Frontend architecture | `docs/spec/frontend-spec.md` |

---

## 14. What NOT to Do

- Do **not** add `created_by`, `user_id`, or `assigned_to` to any Form Request `rules()` method
- Do **not** return raw Eloquent models or arrays from controllers — always use API Resources
- Do **not** put `if ($user->hasRole('admin'))` checks in controllers or services — use `authorize()` / policies
- Do **not** use `response()->json()` — use `new ResourceClass($model)` or `ResourceClass::collection($items)`
- Do **not** use `required` on `Update*Request` fields — use `sometimes`
- Do **not** implement features from a later phase before all earlier phases are complete
- Do **not** store business rules (e.g., allowed status transitions) outside the model — keep them as model constants
- Do **not** call `AuditLog::record()` manually — the `Auditable` trait handles it automatically
- Do **not** use inline `where()` chains in services for filterable queries — delegate to Filter classes
- Do **not** hardcode user-facing strings in controllers, services, or resources — use `__('messages.key')` with the `lang/en/messages.php` and `lang/ar/messages.php` files
- Do **not** add new locales beyond `en` and `ar` without updating `Rule::in(['en', 'ar'])` in all three locale-validating Form Requests (`UpdateSettingsRequest`, `StoreUserRequest`, `UpdateUserRequest`) and running `php artisan lang:add <locale>`

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.14
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `pest-testing` — Tests applications using the Pest 4 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, browser testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 13

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 13 Structure

- In Laravel 13, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 13 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
- CRITICAL: ALWAYS use `search-docs` tool for version-specific Pest documentation and updated code examples.
- IMPORTANT: Activate `pest-testing` every time you're working with a Pest or testing-related task.

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.
</laravel-boost-guidelines>

---

## 15. Frontend Integration Standards

The CRM frontend is integrated into this Laravel project. Its entry point is `resources/js/main.ts`. When working on frontend-related code, follow these conventions in addition to the API rules above.

### Language and Framework

- Use Vue 3 Composition API exclusively with `<script setup lang="ts">`.
- Use TypeScript strict mode. Do not use `any` unless truly unavoidable; prefer `unknown` with narrowing.
- Use Vite with the `@/` alias pointing to `resources/js/`.

### File and Naming Conventions

| Entity | Convention | Example |
|---|---|---|
| Components | PascalCase `.vue` | `BaseDataTable.vue`, `LeadFormView.vue` |
| Composables | camelCase with `use` prefix | `useDataTable.ts`, `useCrudForm.ts` |
| Services | camelCase with `Service` suffix | `leadService.ts`, `authService.ts` |
| Stores | camelCase and descriptive | `stores/auth.ts`, `stores/ui.ts` |
| Types/interfaces | PascalCase | `Lead`, `PaginatedResponse<T>` |
| Enums/constants | PascalCase types, UPPER_SNAKE_CASE maps | `LeadStatus`, `LEAD_STATUS_COLORS` |
| Views | PascalCase with `View` suffix | `LeadListView.vue`, `DashboardView.vue` |

### Component Architecture

- Frontend code belongs under `resources/js/`.
- Base components belong in `resources/js/components/base/` and use the `Base` prefix.
- Base components accept configuration through props, emit interaction events, and contain no business logic or API calls.
- Document component props with TypeScript interfaces.
- Form components belong in `resources/js/components/form/`; use `v-model` for two-way binding.
- `DynamicForm.vue` renders `FormFieldSchema` definitions and `FormField.vue` delegates to type-specific controls.
- Views contain one route view each, compose shared components, and use composables.
- Views and composables must never call Axios directly.

### Composition API Patterns

Keep `<script setup>` sections ordered as imports, composables, props/emits, reactive state, computed values, functions, and lifecycle hooks.

- Composables should have one concern, receive dependencies as arguments, and return plain refs and functions.
- Prefix composables with `use`, such as `useDataTable`, `usePermission`, and `useToast`.

### Services and API Communication

- Services belong in `resources/js/services/` and are plain objects with typed async functions that use the shared Axios instance from `resources/js/services/api.ts`.
- Services return deserialized data and unwrap API response envelopes internally.
- Never import Axios directly into components or composables.
- Use `FormData` for file uploads and `multipart/form-data` content types.
- Configure the API base URL with `VITE_API_BASE_URL`.
- Interceptors must attach `Accept-Language: <locale>` headers; auth travels in the Laravel session cookie (`withCredentials`) — never add an `Authorization` header.
- Follow Laravel pagination parameters: `page`, `per_page`, `sort_by`, and `sort_dir`.
- Debounce frontend search by 300ms.

### State Management and Routing

- Use Pinia only for global shared state such as authentication, UI preferences, notifications, tags, and custom fields.
- Keep entity list and detail data in local component state through composables.
- Define stores with setup syntax: `defineStore('name', () => { ... })`.
- Persist UI preferences (not auth tokens — auth is cookie-based) in `localStorage`.
- Never mutate store state directly from components; use store actions.
- Lazy-load every route view with `component: () => import('@/views/...')`.
- Name routes consistently, such as `leads.index`, `leads.show`, `leads.create`, and `leads.edit`.
- Use `meta.requiresAuth` and `meta.permission`; navigation guards check authentication and permissions.

### Internationalization and RTL

- All user-facing strings must use `t('key')` from `vue-i18n`; do not hardcode template text.
- Store translations in `resources/js/i18n/locales/en.json` and `resources/js/i18n/locales/ar.json`, organized by module.
- Set `<html dir="rtl" lang="ar">` for Arabic.
- Use CSS logical properties instead of physical directional properties.
- Test components in both LTR and RTL layouts.

### Styling

- Bootstrap 5 is the primary CSS framework; use utility classes before custom CSS.
- Override Bootstrap through CSS custom properties in `resources/js/assets/styles/variables.css`.
- Use `<style scoped>` for component-specific styles.
- Do not use inline styles.
- Use Bootstrap Icons (`bi-*`) for iconography.

### Types, Validation, and Errors

- Define entity interfaces in `resources/js/types/models.ts`.
- Define API wrappers such as `PaginatedResponse<T>` and `ApiResponse<T>` in `resources/js/types/api.ts`.
- Define enums in `resources/js/types/enums.ts` and form schemas in `resources/js/types/forms.ts`.
- Strictly type service return values, composable returns, and component props.
- Use Zod for client-side validation on blur and submit.
- Map backend 422 validation errors to form fields.
- Axios interceptors handle global 401, 403, and 500 errors.
- Use `useToast()` for user-facing messages; never silently swallow errors.
- Log unexpected errors in development.

### Permissions and Performance

- Store backend permissions in `authStore.permissions` as a string array.
- Use `usePermission()` for template-level checks and `meta.permission` for route-level checks.
- Use the API's granular permission names, such as `view-customers`, `create-opportunities`, and `update-products`.
- Hide UI actions the user cannot perform instead of merely disabling them.
- Lazy-load route views, use `v-once` for static content, and use `shallowRef` for large lists where deep reactivity is unnecessary.
- Poll notifications every 60 seconds, configurable through an environment variable.

### Frontend Project References

- Frontend entry point: `resources/js/main.ts`.
- Frontend source root: `resources/js/`.
- Backend API specification: `docs/spec/`.
- Frontend architecture specification: `docs/spec/frontend-spec.md`.
