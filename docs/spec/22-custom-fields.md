# 22 — Custom Fields

Custom fields let administrators add typed data to supported CRM records without adding one database column per field. Definitions belong to one custom-field group and values are stored as JSON on the owning record.

> This specification supersedes the original entity-type/EAV description. The current implementation supports **product, customer, and opportunity** model scopes, plus an independent category scope for products. See [32-custom-field-groups.md](32-custom-field-groups.md) for implementation decisions and phase history.

---

## Scope and behavior

- Each supported model has at most one model-scoped group.
- Each product category may have at most one category-scoped group.
- Product categories do not inherit fields from their parent or children.
- `product_categories` stores no custom-field values.
- Products use two JSON columns so a model field and category field can use the same machine name without colliding:
  - `custom_fields` — model scope.
  - `category_custom_fields` — selected-category scope.
- Customers and opportunities use `custom_fields`.
- When a product is created, category values are reconciled to the selected category's definitions. When its category changes, values whose names remain in the new category are retained and the other category values are removed. If the product has no category, its category values are cleared.
- Model-level values are unaffected by a category change.

---

## Database

### `custom_field_groups`

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint unsigned` | Primary key |
| `entity_type` | `string(30)` | Registry key: `product`, `customer`, or `opportunity` |
| `scope` | `string(20)` | `entity` or `category` |
| `category_id` | nullable FK | References `product_categories`; used only for category scope; cascades on category deletion |
| `scope_key` | `string(40)` | Derived key: `entity` or `category:<id>` |
| `name` | `string(100)` | Editable group name |
| `description` | nullable `string(255)` | Optional description |
| `created_by` | FK | References `users` |
| timestamps | | |

Unique index: `(entity_type, scope_key)`. `scope_key` is non-null because a MySQL unique index allows multiple NULL values; using `(entity_type, scope, category_id)` would therefore fail to enforce a single entity-scope group.

### `custom_field_definitions`

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint unsigned` | Primary key |
| `group_id` | FK | References `custom_field_groups`; cascades on group deletion |
| `name` | `string(50)` | Machine name; lowercase letters, digits, underscores; unique within its group |
| `label` | `string(100)` | User-facing label |
| `type` | `string(30)` | One of the 13 types below |
| `options` | nullable JSON | Non-empty strings for `select` and `multi_select` |
| `is_required` | boolean | Defaults to `false` |
| `is_filterable` | boolean | Defaults to `false` |
| `sort_order` | integer | Display order |
| `created_by` | FK | References `users` |
| timestamps | | |

Unique index: `(group_id, name)`. The same name may be used by two different groups, including a product model group and a product-category group.

### Value columns

`custom_fields` is a nullable JSON column on customers and opportunities. Products have both `custom_fields` and `category_custom_fields`, also nullable JSON columns. Values use the field's machine name as the JSON key.

---

## Entity registry and models

`config/crm.php` is the source of truth for registered entity types, model/table names, labels, and optional category configuration. Only products define category support. `App\Services\CustomFieldEntityRegistry` resolves an entity type from a model class and supplies the configured table and values column.

`CustomFieldDefinition` belongs to `CustomFieldGroup`; its entity type and scope are resolved through that relation. `HasCustomFields` resolves the model type through the registry and provides model- and category-scoped definitions/values. Product also uses the trait and casts both JSON value columns to arrays.

Existing definitions are backfilled into their model's entity-scoped group during the group migration. The former `entity_type` column on definitions is removed.

---

## Field types

| Type | Server validation | Form control |
|---|---|---|
| `text` | string, max 255 | Text input |
| `textarea` | string, max 2000 | Text area |
| `number` | numeric | Number input, step 1 |
| `decimal` | numeric | Number input, step 0.01 |
| `date` | `Y-m-d` date | Date picker |
| `datetime` | date | Date-time picker |
| `select` | string in configured options | Single select |
| `multi_select` | array; every value must be a configured option | Multiple select |
| `boolean` | boolean | Checkbox |
| `color` | six-digit hex color | Color input |
| `url` | URL, max 255 | URL input |
| `email` | email, max 255 | Email input |
| `phone` | string, max 30, digits/`+`/spaces/parentheses/hyphens | Telephone input |

Select options are required and non-empty when creating or changing a definition to `select` or `multi_select`. A required definition adds the Laravel `required` rule to its value key. On partial entity updates, the dynamic model-scope rules are added only when the request includes `custom_fields`; product category rules are added only when `category_custom_fields` is submitted. Category-required fields are enforced only for the product's selected category.

---

## Value validation and reconciliation

`CustomFieldService::ruleFor()` centralizes type rules. `buildValueRules()` builds rules for `custom_fields.<name>` and `buildCategoryValueRules()` builds rules for `category_custom_fields.<name>` from the definitions in the selected category. `ValidatesCustomFields` shares those rules across product, customer, and opportunity requests. Category value rules are empty when the request has no `category_id`.

`ProductService` is the authoritative category-value reconciliation path:

1. On create, it retains only submitted category values declared by the selected category.
2. On category change, it intersects existing and submitted category values with the new category's field names; submitted values override retained values with the same key.
3. On an unrelated partial update, it leaves omitted category values untouched and filters submitted category values against the current category.
4. A category with no group, or a product with no category, has no category-scoped values.

Reconciliation removes obsolete keys; it does not make a category change fail merely because the new category has a newly required field. Requiredness is enforced when the request submits values for that selected category.

---

## API

All routes are under `/api/v1` and require the authenticated Laravel session.

| Method | URI | Description |
|---|---|---|
| `GET` | `/custom-fields/entities` | Registry-driven list of supported models and category support |
| `GET` | `/custom-fields/{entityType}` | Entity group and its definitions; returns `group: null` and an empty definition list if no group exists |
| `GET` | `/custom-fields/{entityType}/categories/{category}` | The selected category's group and definitions; never inherits |
| `POST` | `/custom-fields/groups` | Create an entity- or category-scoped group |
| `PUT` | `/custom-fields/groups/{group}` | Update the group name and description |
| `DELETE` | `/custom-fields/groups/{group}` | Remove values for the group's definitions, then delete the group and definitions |
| `POST` | `/custom-fields/{entityType}` | Create an entity-scoped definition |
| `POST` | `/custom-fields/{entityType}/categories/{category}/definitions` | Create a definition in a product category's group |
| `GET` | `/custom-fields/definitions/{definition}` | Show a definition |
| `PUT` | `/custom-fields/definitions/{definition}` | Update a definition |
| `DELETE` | `/custom-fields/definitions/{definition}` | Remove the definition's stored key, then delete the definition |

`entityType` routes are constrained to registered keys. An unsupported key such as `banana` does not reach a controller action and returns 404. Creating a second group for an existing model/category target or a duplicate definition name within a group returns 422.

Entity/category group reads have this resource shape:

```json
{
  "data": {
    "group": {
      "id": 12,
      "entity_type": "product",
      "scope": "category",
      "category_id": 7,
      "scope_key": "category:7",
      "name": "Uniform details",
      "description": null
    },
    "definitions": [
      {
        "id": 28,
        "group_id": 12,
        "entity_type": "product",
        "scope": "category",
        "category_id": 7,
        "name": "fabric",
        "label": "Fabric",
        "type": "select",
        "options": ["Cotton", "Wool"],
        "is_required": false,
        "is_filterable": true,
        "sort_order": 0
      }
    ]
  }
}
```

The product category editor creates/updates the product category first, then persists category definitions through these group/definition endpoints. Deleting a field or group removes the corresponding JSON key with `JSON_REMOVE` from rows in the configured entity table and values column.

---

## Filters

Filters are accepted only for definitions marked `is_filterable`. The `CustomFieldFilter` resolves the model type from the query's model and ignores unknown or non-filterable custom-field parameters.

| Parameter | Scope | Behavior |
|---|---|---|
| `cf_<name>` | Model | `custom_fields.<name>` |
| `ccf_<name>` | Category | Product's `category_custom_fields.<name>` |

Text, textarea, email, URL, and phone fields use substring matching. Numeric fields accept numeric equality filters; a non-numeric filter value is ignored. Boolean values are parsed with `filter_var`; other scalar types use equality. A multi-select matches if any selected value is present in the stored JSON array. It accepts either repeated/array values or a comma-separated string, with surrounding whitespace trimmed.

---

## Frontend

- `customFieldService` uses the shared Axios instance; session cookies and `Accept-Language` remain managed centrally.
- The Pinia custom-field store caches groups/definitions by model or category and records whether each key has loaded.
- `useCustomFields(entityType, scope?, categoryId?)` supplies definitions and their `DynamicForm` schema.
- `CUSTOM_FIELD_TYPE_MAP` maps each backend type to a reusable form control and any numeric step.
- `DynamicForm` supports dot-path keys such as `custom_fields.size` and `category_custom_fields.size`, so values and backend 422 errors bind to the right inputs.
- `FormSelect` supports actual multiple selection. `CustomFieldValue` formats field values for detail views.
- `CustomFieldFilterBar` renders filterable definitions for both scopes.
- The Settings → Custom Fields page manages one group per model. Product-category definitions are edited inline in the category form. Custom fields are appended inline with the entity's regular fields.
- Product detail includes Details and Opportunities tabs. Opportunity rows are plain text because products do not expose a product-specific Opportunity route. Products do not show notes, timeline, or attachments.

English and Arabic strings are in `resources/js/i18n/locales/en.json` and `ar.json`; controls use Bootstrap and the generated layout uses logical/Bootstrap RTL behavior.

---

## Authorization

Viewing definitions/groups is available to authenticated users. Group and definition mutations use the existing granular permissions:

| Action | Permission |
|---|---|
| Create a group/definition | `create-custom-fields` |
| Update a group/definition | `update-custom-fields` |
| Delete a group/definition | `delete-custom-fields` |

No `manage-custom-fields` permission is used. Product and related product-detail routes separately require `view-products`; product edits require `update-products`.

---

## Tests

Pest feature tests cover group uniqueness and authorization, the registry and group migration, all field types/options/requiredness, independent model/category scopes, no inheritance, category reconciliation, JSON cleanup, both filter prefixes and operators, and product detail/Opportunity endpoints. Frontend validation is currently `vue-tsc` plus the Vite production build (`npm run build`); this repository does not configure a Vue unit-test runner.
