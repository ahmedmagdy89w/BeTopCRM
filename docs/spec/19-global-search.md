# 19 — Global Search

A single endpoint that searches across leads, customers, organizations, and contacts simultaneously. Returns grouped, ranked results.

---

## API Endpoint

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/search` | `SearchController@index` | Global search across all entities |

### Query Parameters

| Param | Type | Description |
|---|---|---|
| `q` | `string` | **Required.** Search query (min 2 chars) |
| `types` | `string` | Comma-separated entity types to search. Default: all. Options: `leads,customers,organizations,contacts` |
| `limit` | `int` | Max results per entity type (default: 5, max: 20) |

---

## Validation

### GlobalSearchRequest

```php
public function rules(): array
{
    return [
        'q'     => ['required', 'string', 'min:2', 'max:100'],
        'types' => ['nullable', 'string'],
        'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
    ];
}
```

The `types` field is validated to only contain allowed values after splitting by comma.

---

## Response

```json
{
  "query": "acme",
  "results": {
    "leads": {
      "count": 3,
      "items": [
        {
          "id": 5,
          "type": "lead",
          "title": "Acme Corp Opportunity",
          "subtitle": "john@acme.com",
          "status": "qualified",
          "url": "/leads/5",
          "matched_field": "company"
        }
      ]
    },
    "customers": {
      "count": 1,
      "items": [
        {
          "id": 12,
          "type": "customer",
          "title": "Acme Corp",
          "subtitle": "jane@acme.com — Active",
          "status": "active",
          "url": "/customers/12",
          "matched_field": "company_name"
        }
      ]
    },
    "organizations": {
      "count": 1,
      "items": [
        {
          "id": 3,
          "type": "organization",
          "title": "Acme Corporation",
          "subtitle": "Technology — New York",
          "url": "/organizations/3",
          "matched_field": "name"
        }
      ]
    },
    "contacts": {
      "count": 2,
      "items": [
        {
          "id": 8,
          "type": "contact",
          "title": "John Smith",
          "subtitle": "VP Engineering — Acme Corporation",
          "url": "/organizations/3/contacts/8",
          "matched_field": "organization"
        }
      ]
    }
  },
  "total_count": 7
}
```

---

## SearchResource

**File:** `app/Http/Resources/Search/SearchResultResource.php`

Each result item is a normalized shape regardless of entity type:

| Field | Description |
|---|---|
| `id` | Entity ID |
| `type` | Entity type (`lead`, `customer`, `organization`, `contact`) |
| `title` | Primary display name |
| `subtitle` | Secondary info (email, status, org name, etc.) |
| `status` | Status if applicable, null otherwise |
| `url` | Relative URL to the entity |
| `matched_field` | Which field matched the query |

---

## SearchService

**File:** `app/Services/SearchService.php`

| Method | Signature | Description |
|---|---|---|
| `search` | `search(string $query, array $types, int $limit, User $user): array` | Run parallel queries, merge and return grouped results |

### Search Fields Per Entity

| Entity | Fields Searched |
|---|---|
| Lead | `first_name`, `last_name`, `email`, `phone`, `company` |
| Customer | `company_name`, `contact_name`, `contact_email`, `contact_phone` |
| Organization | `name`, `email`, `phone`, `website` |
| Contact | `first_name`, `last_name`, `email`, `phone` |

### Business Rules

- Uses `LIKE %query%` for each field — simple and sufficient for moderate data sizes.
- Each entity type query is scoped by the user's permissions (e.g., sales reps only see their assigned leads).
- Results are limited per type (default 5) to keep the response fast.
- Empty types get an empty `items` array and `count: 0`.
- The `matched_field` value indicates which field caused the match (first match wins).

### Performance Note

For databases with >100K records, consider replacing `LIKE` with MySQL full-text indexes (`FULLTEXT`) or a dedicated search engine (Meilisearch/Algolia). The service interface stays the same.

---

## Permissions

Global search only returns entities the authenticated user has permission to view — no additional permissions needed.
