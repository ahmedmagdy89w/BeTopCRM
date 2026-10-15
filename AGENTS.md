<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/ai (AI) - v0
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

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

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter


- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>

---

# CRM Project Instructions

These rules apply to every agent, coding assistant, model, and provider working in this repository. The repository code, tests, configuration, and specifications are the source of truth.

## Project Context

- This is a Laravel 13 CRM application with an integrated Vue 3 frontend.
- Backend specifications are in `docs/spec/`; the frontend specification is `docs/spec/frontend-spec.md`.
- The frontend entry point is `resources/js/main.ts`; frontend source code belongs under `resources/js/`.
- PHP is 8.3+, the API uses Sanctum 4, and all API routes use the `/api/v1` prefix.
- Supported locales are `en` and `ar`. User-facing backend strings use `__('messages.key')`; frontend strings use `vue-i18n`.
- Authentication uses Laravel session cookies. Frontend code must not persist or manually attach bearer tokens.

## Universal Agent Rules

- Inspect the relevant specification and existing sibling files before changing code.
- Preserve user changes and unrelated worktree changes.
- Make the smallest correct change and do not add dependencies without approval.
- Do not introduce provider-specific SDKs, tools, or agent configuration unless explicitly requested.
- If required files, tools, or documentation are unavailable, state the limitation instead of inventing behavior.
- Every behavior change requires an appropriate test and verification.

## Backend Architecture

- Controllers are thin: authorize, call one service method, and return an API Resource.
- Controllers must not contain business logic or call `DB::`/Eloquent directly.
- Every controller action calls `$this->authorize()` before the service.
- Business logic belongs in constructor-injected services under `app/Services/`.
- Filterable queries use dedicated classes under `app/Filters/`; do not build conditional `where()` chains in services.
- Every controller response uses an API Resource from `app/Http/Resources/`; never return raw models or `response()->json()`.
- Form Requests belong under `app/Http/Requests/{Entity}/`. Update requests use `sometimes`; server-owned fields such as `created_by`, `user_id`, and `assigned_to` are never request inputs.
- Models use `HasFactory`; entity models use `SoftDeletes`; casts, scopes, policies, events, jobs, and traits must follow the existing project conventions.
- Use policies for authorization. Do not hardcode role checks in controllers or services.
- Use granular permissions such as `create-leads`, `update-leads`, `delete-leads`, and `view-leads`; never add new `manage-*` permissions.
- Queue long-running work such as imports, exports, webhooks, and campaign email delivery.
- Audit logs are append-only and are recorded by the `Auditable` trait, not manually from controllers or services.
- Validate status transitions before persisting and use database-level aggregation for reports to avoid N+1 queries.

## Testing and Formatting

- Use Pest 4 syntax and Laravel factories.
- Use `RefreshDatabase` for database tests and cover happy paths, authorization failures, and validation failures.
- Add dedicated authorization tests for policy and permission behavior.
- Run the smallest relevant test command, such as `php artisan test --compact --filter=testName`.
- For PHP changes, run `vendor/bin/pint --dirty --format agent` before finalizing.

## Frontend Architecture

- Use Vue 3 Composition API exclusively with `<script setup lang="ts">` and strict TypeScript.
- Use Vite with the `@/` alias pointing to `resources/js/`.
- Components use PascalCase; composables use the `use` prefix; services use camelCase with a `Service` suffix; views use the `View` suffix.
- Base components belong in `resources/js/components/base/` and contain no business logic or API calls.
- Form components belong in `resources/js/components/form/` and use `v-model` for two-way binding.
- Views compose components and composables. Views and composables never call Axios directly.
- Services under `resources/js/services/` use the shared `resources/js/services/api.ts` Axios instance and return deserialized data.
- Pinia is for global state only: auth, UI preferences, notifications, tags, and custom fields. Entity lists and details stay in local composable/component state.
- Stores use setup syntax and components use store actions instead of directly mutating store state.
- Lazy-load route views, name routes consistently, and use `meta.requiresAuth` and `meta.permission` for access control.
- All user-facing text uses `t('key')` from Vue I18n. Translation files are `resources/js/i18n/locales/en.json` and `resources/js/i18n/locales/ar.json`.
- Support both LTR and RTL. Set the document direction and language for Arabic and prefer CSS logical properties.
- Bootstrap 5 is the primary CSS framework. Use utility classes first, scoped styles second, CSS variables in `resources/js/assets/styles/variables.css`, and Bootstrap Icons for icons. Do not use inline styles.
- Use Zod/VeeValidate for client-side validation and map backend 422 errors to form fields.
- The Axios client sends `Accept-Language` and uses the Laravel session cookie via `withCredentials`; it handles global authentication errors.
- Store permissions in `authStore.permissions`, check them with `usePermission()`, and hide unauthorized actions rather than merely disabling them.
- Debounce search by 300ms, lazy-load route views, use `shallowRef` for large lists where appropriate, and poll notifications every 60 seconds when enabled.

## Documentation References

Before implementing a feature, read the relevant document in `docs/spec/`:

- `01-domain-model.md`: database schema and relationships
- `02-api-endpoints.md`: API routes and response behavior
- `03-models.md` through `06-services.md`: models, validation, resources, and services
- `07-events.md` through `10-roles-permissions.md`: events, file structure, phases, and authorization
- `11-reports.md` through `18-import-export.md`: reports, notifications, tasks, tags, campaigns, audit logs, attachments, and import/export
- `19-global-search.md` through `28-activity-timeline.md`: search, dashboard, settings, custom fields, scoring, products, webhooks, forms, notes, and timelines
- `29-lead-products.md` through `31-ai-email-generator.md`: lead products, company settings, and AI email generation
- `frontend-spec.md`: Vue routes, components, services, state, i18n, RTL, permissions, and frontend conventions
