# 32 — Custom Field Groups (Model-Level + Category-Level)

**Status:** complete
**Supersedes:** `22-custom-fields.md` (which documents entities that no longer exist, a route that never matched, and a `manage-custom-fields` permission that was replaced by `create-/update-/delete-custom-fields`)

Admin-defined custom fields, scoped to a model **or** to a product category, stored as JSON. Admin can declare fields from the UI, they render inline in the normal entity forms, they participate in list filters, and they are validated on the server by type, options, and requiredness.

---

## Current state (the gap this closes)

- `custom_field_definitions` exists with `entity_type` / `type` / `options` / `is_required` / `is_filterable`, but there is **no group concept**, and `ENTITY_TYPES` omits products — which have no `custom_fields` column at all.
- **Zero value validation.** `CustomFieldService::validateValues()` and `HasCustomFields::validateCustomFields()` are dead code. Requiredness, type, and option membership are all unenforced; services write the JSON blob verbatim.
- **Zero frontend.** `stores/customFields.ts` and `services/customFieldService.ts` exist but no view imports them, and `customFieldService.create()` posts to a URL that does not exist.
- **Filters:** only `CustomerFilter.php:71-76` handles `cf_*`, and it ignores `is_filterable` entirely.
- `entity_type` is unvalidated on index/store, so `/custom-fields/banana` reaches `tableForEntity()` and 500s. A duplicate `(entity_type, name)` also 500s instead of returning 422.
- `FormSelect.vue` is single-select only — no multi-select exists.
- `FormFieldDefinition` factory is declared via `HasFactory` but does not exist.

---

## Decisions

| Decision | Choice |
|---|---|
| Model group rule | One group per model, enforced by a DB unique index |
| Category group rule | One group per product category |
| Category hierarchy | **No inheritance** — every category is fully independent |
| Category fields managed from | The **category add/edit form**, so they never get confused with per-model fields |
| Category entity itself | `product_categories` gets **no** `custom_fields` column |
| Supported models | `product`, `customer`, `opportunity` |
| Value storage on `products` | Two columns: `custom_fields` (model scope) + `category_custom_fields` (category scope) |
| On category change | Keep values that still apply, drop the rest |
| Filter params | `cf_<name>` (model scope), `ccf_<name>` (category scope) |
| Field types | 13: existing 8 + `textarea`, `decimal`, `color`, `phone`, `datetime` |
| Required | Per field, per scope; a category field is required only when the chosen category declares it |
| Rendering | **Inline** in the same form grid, no separate section or card |
| Multi-select | Supported, with server-side option validation |
| Detail view shell | Match the existing hand-rolled idiom; do not refactor the large existing pages |
| Permissions | Reuse existing `create-/update-/delete-custom-fields` — no new permissions, no seeder or role changes |
| Tests | MySQL (`crm_test`), so the existing `JSON_REMOVE` cleanup is portable-enough as-is |

### Why two value columns, not one merged map

A model-level `size` and a category-level `size` can legitimately coexist. One map would collide silently. Two columns keep reads, writes, and filter resolution unambiguous.

### Why definitions still live in one table

Storage of a *field definition* is identical for both scopes — only the management UI differs. Keeping them in one table is what lets validation, filtering, and rendering be shared code rather than two parallel implementations.

---

## Architecture: one registry, three extension points

The whole point is that a fifth model is cheap. Duplicating per-model knowledge across six places is what would make this unmaintainable, so it is centralised.

### 1. `config/crm.php` → the entity registry

`config/crm.php` already exists and is actively used (`crm.attachments`, `crm.lead_scoring`, `crm.ai`). Model classes are stored as FQCN **strings** so `config:cache` stays safe.

```php
'custom_fields' => [
    'entities' => [
        'product' => [
            'model' => \App\Models\Product::class,
            'table' => 'products',
            'label' => 'products.title',
            'category' => [
                'table' => 'product_categories',
                'foreign_key' => 'category_id',
                'label' => 'products.categories',
                'values_column' => 'category_custom_fields',
            ],
        ],
        'customer' => [...],
        'opportunity' => [...],
    ],
],
```

`category` is simply **absent** on the other three, so "supports categories" is configuration, not a code branch.

### 2. `app/Services/CustomFieldEntityRegistry.php`

Thin, constructor-injected, sits beside `CustomFieldService` (no new base folder).

```
all() · has() · guard() · types()
modelClass() · table() · typeForModel()          // Product::class => 'product'
hasCategoryScope() · categoryTable() · categoryForeignKey() · categoryValuesColumn()
resolveScope(entityType, scope, categoryId)      // normalized target descriptor
```

`typeForModel()` is what lets `HasCustomFields` drop its `abstract getCustomFieldEntityType()` entirely.

### 3. Three extension points

| Extension point | Per-model cost |
|---|---|
| `HasCustomFields` auto-resolves its entity type from `static::class` | `use HasCustomFields;` |
| `CustomFieldFilter` auto-resolves the entity type from `$query->getModel()` | one line: `$query = (new CustomFieldFilter($filters))->apply($query);` |
| `useCustomFields(entityType, scope?, categoryId?)` composable | `...cf.schema.value` spread into the existing schema array |

Adding a model then costs: one config entry + one column migration + `use HasCustomFields` + one line in two requests + one line in its filter. No type maps, no filter operators, no Vue, no admin UI.

### Duplication audit — where each fact lives exactly once

| Knowledge | Naive | This design |
|---|---|---|
| Which models support custom fields | 6+ places | `config/crm.php` |
| `entity_type` → table (for `JSON_REMOVE`) | `tableForEntity()` match | `registry->table()` |
| `entity_type` on a model instance | 4 identical method bodies | `registry->typeForModel()` |
| 13 types → Laravel rules | inline per request | `CustomFieldService::ruleFor()` |
| Injecting rules into a request | 6 copied blocks | `ValidatesCustomFields` trait |
| 13 types → JSON filter operator | per filter class | `CustomFieldFilter::applyValueFor()` |
| Strip a key from entity rows on delete | 2 copies of the SQL | `CustomFieldService::stripKeyFromRows()` |
| 13 types → Vue control | per-view maps | `CUSTOM_FIELD_TYPE_MAP` |
| Render a value by type | per detail view | `CustomFieldValue.vue` |
| Render filter controls | per list view | `CustomFieldFilterBar.vue` |
| Edit field **definitions** | category form + admin page | `CustomFieldDefinitionEditor.vue` |
| Admin model dropdown | hard-coded `<option>`s | `GET /custom-fields/entities` |

---

## Field types (13)

| Type | Validation rule | Form control |
|---|---|---|
| `text` | `string\|max:255` | text |
| `textarea` | `string\|max:2000` | textarea |
| `number` | `numeric` | number, `step=1` |
| `decimal` | `numeric` | number, `step=0.01` |
| `date` | `date_format:Y-m-d` | date picker |
| `datetime` | `date` | datetime picker |
| `select` | `string` + `Rule::in(options)` | single select |
| `multi_select` | `array`, each `Rule::in(options)` | multi select |
| `boolean` | `boolean` | checkbox |
| `color` | `string` + `/^#[0-9A-Fa-f]{6}$/` | swatch + hex input |
| `url` | `url\|max:255` | url |
| `email` | `email\|max:255` | email |
| `phone` | `string\|max:30\|regex:/^[0-9+\-\s()]+$/` | tel |

Every rule is prefixed with `required` when `is_required` is set. `options` is required and non-empty for `select` / `multi_select`.

Filter operators: `like` for text-ish, `(float)` for numeric, `filter_var` for boolean, `whereDate` for dates, `whereJsonContains` (+ comma-split `orWhereJsonContains`) for `multi_select`, exact for `select` / `color`.

---

## Phases

Legend: `[ ]` not started · `[~]` in progress · `[x]` done

### [x] Phase 1 — Schema & registry

- [x] `create_custom_field_groups_table` — `entity_type`, `scope` (`entity`|`category`), `category_id` (nullable FK → `product_categories`, `cascadeOnDelete`), `scope_key` (derived: `'entity'` or `'category:7'`), `name`, `description?`, `created_by`, timestamps
  - Unique `(entity_type, scope_key)`. `scope_key` exists because MySQL treats `NULL`s as distinct, so a unique on `(entity_type, scope, category_id)` would silently allow two entity-scope groups. This index **is** the one-group rule.
- [x] `restructure_custom_field_definitions_for_groups` — add `group_id` FK `cascadeOnDelete`; drop `entity_type`; drop `..._entity_type_name_unique`; add unique `(group_id, name)`
- [x] `add_custom_fields_to_products_table` — two nullable JSON columns: `custom_fields`, `category_custom_fields`
- [x] `config/crm.php` — add `custom_fields.entities`
- [x] `app/Services/CustomFieldEntityRegistry.php`
- [x] `app/Models/CustomFieldGroup.php` — `HasFactory`, `creator()`, `definitions()` HasMany, `scopeForTarget()`
- [x] `app/Models/CustomFieldDefinition.php` — add `group()`; drop `ENTITY_TYPES`; `scopeForEntity` → `whereHas('group', …)`; `TYPES` → 13
- [x] `app/Models/Traits/HasCustomFields.php` — delete the `abstract getCustomFieldEntityType()`; add `customFieldEntityType()` via the registry
- [x] `app/Models/Product.php` — trait + both columns in `$fillable`
- [x] `database/factories/CustomFieldGroupFactory.php`, `CustomFieldDefinitionFactory.php`

> **Deviation:** the `getCustomFieldEntityType()` bodies were deleted from `Customer`/`Opportunity`/`Deal` rather than merely made non-abstract, since the registry now resolves all four. `HasCustomFields::initializeHasCustomFields()` also casts the category values column (read from `config()`, not the container, because trait initializers run inside `Model::__construct`).

### [x] Phase 2 — Validation

- [x] `CustomFieldService::ruleFor()` — single `match` over the 13 types
- [x] `CustomFieldService::buildValueRules()` / `buildCategoryValueRules()`
- [x] `app/Http/Requests/CustomField/ValidatesCustomFields.php` — `customFieldRules($entityType)` + `categoryCustomFieldRules($entityType)` (returns `[]` when no `category_id` submitted)
- [x] `Store/UpdateProductRequest` — +2 lines each
- [x] `Store/UpdateCustomerRequest`, `Store/UpdateOpportunityRequest`, `Store/UpdateDealRequest` — +1 line each
- [x] `StoreCustomFieldDefinitionRequest` — add `Rule::unique` on `(group_id, name)` (duplicate currently 500s)
- [x] Derive `entity_type` from the group server-side; never trust it from the request

> **Deviation:** `entityGroup()`/`categoryGroup()` take an explicit `$createdBy` instead of calling `auth()->id()`, because the service is also reachable from console and queue contexts and `created_by` is non-nullable. The controller and `StoreCustomFieldDefinitionRequest` pass the request user.

### [x] Phase 3 — Category-change reconciliation

- [x] `CustomFieldService::reconcileCategoryValues()` — intersect incoming values with the target category's field names
- [x] Enforce in `ProductService::create()` / `update()` (authoritative)
- [x] No inheritance: a category with no group yields `[]`

> **Note:** reconciliation deliberately does not enforce required-ness, so re-categorising a product is never blocked by a field the new category just made required. Required-ness stays in the form request, which only validates what the client actually submitted.
>
> Three cases are handled rather than one: create reconciles against the target category; a category *change* keeps stored values whose names survive and merges incoming; an unrelated partial update leaves stored values alone so a PUT that omits them cannot silently wipe them. Empty results are stored as `NULL`, not `[]`, so the JSON column stays an object rather than flipping to an array.

### [x] Phase 4 — Filters

- [x] `app/Filters/CustomFieldFilter.php` — self-resolving entity type, handles `cf_` and `ccf_`, one `match` for operators, honors `is_filterable`
- [x] `CustomerFilter::apply()` — delete the 6 inline `cf_` lines, add 1
- [x] `app/Filters/ProductFilter.php` + `app/Filters/OpportunityFilter.php` — move the services' inline `where()` chains out (AGENTS.md mandates filter classes)
- [x] Wire up the `sort_by` / `sort_dir` the frontend already sends and the server ignores
- [x] No deal filter — `DealService::list(Customer)` takes no filters and there is no deal index route

> **Notes:** `is_filterable` is now actually honoured — previously a `cf_` param for a non-filterable field still filtered. An unknown or non-filterable field is *ignored* rather than rejected, so a stale bookmarked URL degrades to an unfiltered list. `sort_by` goes through a fixed allowlist in `BaseFilter::applySort()` and an unknown column silently falls back to the default, so user input never reaches `orderBy`. Multi-select matches rows containing *any* selected value (widening), not all. `CustomFieldService::categoryScopedDefinitions()` was added so a `ccf_` filter can match products in whichever category declares that field.

### [x] Phase 5 — API

- [x] `CustomFieldGroup` controller / resource / store+update requests / policy
- [x] `GET /custom-fields/entities` — registry-driven model list
- [x] `GET /custom-fields/{entityType}` → `{ group, definitions }`
- [x] `GET /custom-fields/{entityType}/categories/{category}`
- [x] `GET|POST /custom-fields/{entityType}`, `GET|PUT|DELETE /custom-fields/definitions/{definition}`
- [x] `->whereIn('entityType', ...)` route constraint
- [x] `stripKeyFromRows()` shared by field-delete and group-delete

> Group metadata is created with `POST /custom-fields/groups`, updated with `PUT /custom-fields/groups/{group}`, and deleted with `DELETE /custom-fields/groups/{group}`. The API reuses the existing granular create/update/delete custom-field permissions. Entity/category reads return a null group and an empty definition list when no group exists; reads do not create groups.

### [x] Phase 6 — Frontend

- [x] `resources/js/composables/useCustomFields.ts` — `useCustomFields(entityType, scope?, categoryId?)`
- [x] `CUSTOM_FIELD_TYPE_MAP` — 13 entries → `{ type, step? }`; no view branches on a type
- [x] `resources/js/stores/customFields.ts` — rewrite with a `loaded` cache guard
- [x] `resources/js/services/customFieldService.ts` — rewrite; fix the broken `create()` URL
- [x] `DynamicForm.vue` — dot-path key support in its 3 read/write spots (enables inline nesting **and** automatic binding of backend 422 keys)
- [x] `FormSelect.vue` — true multi-select
- [x] `FormField.vue` — `color` branch, honour `step`
- [x] `components/shared/CustomFieldValue.vue` — value display by type
- [x] `components/shared/CustomFieldFilterBar.vue` — filter controls, both scopes
- [x] `components/shared/CustomFieldDefinitionEditor.vue` — the definitions editor
- [x] `views/settings/CustomFieldGroupsView.vue` — model dropdown, one group per model
- [x] Router + `Sidebar.vue` + `nav.customFields` + extended `customFields.*` in `en.json` and `ar.json`
- [x] `ProductFormView.vue` → convert to `DynamicForm`; category select change drives the `catCf` swap and value pruning
- [x] `ProductCategoryListView.vue` — inline category form gains a "Product fields" section
- [x] `ProductListView.vue` — `<CustomFieldFilterBar entity-type="product" @change="onFilter" />`
- [x] `Customer/Opportunity` form + list + detail views — custom fields are connected

> **Deviation:** the category editor persists fields through the category-scoped `/custom-fields/{entityType}/categories/{category}/definitions` endpoint instead of embedding `product_fields[]` in category create/update. This keeps category definitions under the same definition service, authorization, and cleanup rules as model-scoped fields. Product requests prune the category values to the newly loaded schema on category changes; the service remains authoritative.

### [x] Phase 7 — ProductDetailView.vue

- [x] `views/products/ProductDetailView.vue` — `details | deals | opportunities`
- [x] `ProductService::find()` / `listDeals()` / `listOpportunities()`
- [x] `ProductController::show` calls the service (clears a thin-controller violation); 2 new thin actions
- [x] `ProductResource` — both custom-field columns + `whenPivotLoaded('deal_items', …)`
- [x] `GET products/{product}/deals`, `GET products/{product}/opportunities`
- [x] `products.show` route; `ProductListView` row-click retargets, pencil stays on edit
- [x] No notes / timeline / attachments tabs — `TimelineService.php:22-24` whitelists only customers/opportunities/deals and `Product` uses none of those traits
- [x] Deal rows are plain text — no `deals.show` route exists

### [x] Phase 8 — Tests

- [x] Model group: create; second group for same model → 422; bad model in URL → 404 through the route constraint (not 500); all 13 types; duplicate name → 422; options required + membership enforced
- [x] Category fields: the category-scoped definitions endpoint creates the group/definitions; shared definition update/delete routes modify and remove category fields; a category with no group yields no fields
- [x] Scope independence: model-level `size` and category-level `size` coexist
- [x] No inheritance: child category with no group has no fields even though its parent has fields
- [x] Requiredness enforced only for fields the chosen category declares; no category → nothing required
- [x] Category change: pants→t-shirts keeps `size`; a category without matching definitions drops stored category values
- [x] Filters: `cf_` and `ccf_`; non-filterable ignored; `multi_select` containment; text partial; comma-separated multi-select values
- [x] `group delete` strips keys from existing rows — asserted on product JSON
- [x] 403 for manager and sales_rep on groups and fields
- [x] Corrected the two legacy custom-field validation test names; their payloads already used `name` / `label` / `type`
- [x] `ProductDetailTest`: show returns category/creator/both custom-field columns; the two new endpoints return correct pivot data; `view-products` enforced
- [x] `vendor/bin/pint --dirty` + focused custom-field/product tests; `npm run build`

> **Verification note:** this repository has no frontend test runner configured. Vue/TypeScript behavior is type-checked and bundled by `npm run build`; the backend endpoint, validation, authorization, reconciliation, and filter behaviors have Pest feature coverage.

### [x] Phase 9 — Docs

- [x] Rewrite `22-custom-fields.md` to match the shipped storage, scopes, types, validation, APIs, filters, permissions, and frontend behavior
- [x] Update `01-domain-model.md`, `02-api-endpoints.md`, `08-file-structure.md`, `frontend-spec.md`

---

## Execution order

| Phase | Depends on |
|---|---|
| 1 | — |
| 2, 4, 5 | 1 (mutually independent) |
| 3 | 1 |
| 6 | 2, 4, 5 |
| 7 | 6 |
| 8 | 2–7 |
| 9 | 8 |

---

## Progress log

_Newest first._

- **Phase 9 done — feature plan complete.** Rewrote `22-custom-fields.md` for the shipped group/category model; synchronized the domain relationship/storage notes, API route inventory, current custom-field files, frontend routes/components/cache/type map, and product detail spec. Full suite: **509 passed / 1 pre-existing Quotation numbering failure**. The focused custom-field/product suites pass; Pint and Vite build pass.
- **Phase 8 done** — added/expanded backend coverage for duplicate model/category groups, all supported field types, options and value validation, scoped category definition create/update/delete, requiredness, no inheritance, scope independence, category reconciliation, `cf_`/`ccf_` filters (including comma-separated multi-select), cleanup on definition/group deletion, manager/sales-rep authorization, and ProductDetail API responses/pivots. The focused custom-field/product set is green; full suite **506 passed / 1 pre-existing Quotation numbering failure**. `vendor/bin/pint --dirty --format agent` passes; `npm run build` passes.
- **Phase 7 done** — added `ProductService::find/listDeals/listOpportunities`, thin authorized controller actions, `ProductResource` custom field/creator/category/pivot fields, and `ProductDetailView` with Details/Deals/Opportunities tabs. Product list rows navigate to the detail page; the edit pencil remains on edit. Related deal/opportunity rows are plain text, with pivot quantities/prices exposed; the detail view has no notes/timeline/attachments tabs. `tests/Feature/ProductDetailTest.php` + existing product tests: **25 passed / 68 assertions**. Frontend build passes. Full suite: **503 passed / 1 pre-existing Quotation numbering failure**.
- **Phase 6 done** — added `useCustomFields`, the 13-type map, loaded-aware Pinia cache, typed service, nested dot-path binding, real multi-select, color/step input support, value/filter/editor components, settings route/sidebar/i18n, product form/category editor integration, and customer/opportunity forms, lists, and details. Category field definitions are saved through the scoped API from Phase 5. `npm run build` passes; all custom-field backend suites pass: **129 tests / 286 assertions**.
- **Phase 5 done** — added `CustomFieldGroupController`, resources, store/update requests, `CustomFieldGroupPolicy`, registry-driven entities listing, entity/category group reads, group metadata CRUD, and constrained routes ordered ahead of dynamic entity paths. Group deletion calls the same `stripKeyFromRows()` as definition deletion for every group definition. Added the category-definition create endpoint for the inline category editor. `tests/Feature/CustomFieldGroupApiTest.php` (18 tests) covers route matching/constraints, response shape, independent categories, category writes, granular permissions, duplicate target validation, and cleanup. All custom-field suites: **129 passed / 286 assertions**. Full suite before Phase 6: **497 passed / 1 pre-existing Quotation numbering failure**.
- **Phase 4 done** — `CustomFieldFilter` resolves its own entity type and values column from the registry, so `CustomerFilter`, `ProductFilter`, and `OpportunityFilter` each apply it with one line. `ProductService::list` and `OpportunityService::list` no longer build query chains inline. `sort_by`/`sort_dir` are now honoured via an allowlist. `tests/Feature/CustomFieldFilterTest.php` (19 tests) covers every comparison mode, `cf_` vs `ccf_` column separation, `is_filterable`, unknown fields/columns, and preserved opportunity behaviour. Suite **481 passed / 1 pre-existing failure**.
- **Phase 3 done** — `reconcileCategoryValues()` plus the three-way reconciliation in `ProductService` (create / category changed / category unchanged). `tests/Feature/CustomFieldCategoryReconciliationTest.php` (14 tests) proves the documented Pants→T-Shirts case keeps `size` and drops `inseam`, that a partial update cannot wipe stored values, and that unknown keys never reach storage. Suite **462 passed / 1 pre-existing failure**.
- **Phase 2 done** — group-aware `CustomFieldService` (`ruleFor`, `buildValueRules`, `buildCategoryValueRules`, `validateValues`, `stripKeyFromRows`, on-demand group creation with a race-safe retry) plus the shared `ValidatesCustomFields` trait wired into 8 entity requests. `StoreCustomFieldDefinitionRequest` now scopes `Rule::unique` to the group, so a duplicate name is a 422 instead of a 500, and the same name is legitimately reusable in a different group. `tests/Feature/CustomFieldValidationTest.php` (44 tests) covers all 13 types, required/optional, multi-select option whitelisting, per-category required enforcement, and same-name model vs category independence. Suite back to **448 passed / 1 pre-existing failure**.
- **Phase 1 done** — 3 migrations, registry, `CustomFieldGroup`, group-aware `CustomFieldDefinition`, trait, `Product`, and both factories. `tests/Feature/CustomFieldGroupTest.php` (32 tests) covers the registry, the two unique indexes, scope-key derivation, and value-column separation.
- **Plan agreed** — scope, storage, filters, rendering, and admin UX settled with the user.
