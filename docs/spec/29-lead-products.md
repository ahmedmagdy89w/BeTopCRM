# 29 — Lead Products

Associates products from the catalog with a Lead to track which products/services a prospect is interested in. Products are **linked** from the existing catalog — they are never created or modified through this relationship.

---

## Database

### `lead_products` (pivot)

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint unsigned` | PK |
| `lead_id` | `foreignId` | → `leads`, cascade on delete |
| `product_id` | `foreignId` | → `products`, cascade on delete |
| `quantity` | `integer` | Default `1`, min `1` |
| `notes` | `text` | Nullable — line-item notes |
| `created_at` | `timestamp` | |
| `updated_at` | `timestamp` | |

**Unique constraint:** `(lead_id, product_id)` — a product can only be attached once per lead.

---

## Models

### Lead (addition)

```php
public function products(): BelongsToMany
{
    return $this->belongsToMany(Product::class, 'lead_products')
        ->withPivot('quantity', 'notes')
        ->withTimestamps();
}
```

### Product (addition)

```php
public function leads(): BelongsToMany
{
    return $this->belongsToMany(Lead::class, 'lead_products')
        ->withPivot('quantity', 'notes')
        ->withTimestamps();
}
```

---

## API Endpoints

All endpoints require `auth:sanctum`. Authorization delegates to `LeadPolicy`.

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/leads/{lead}/products` | `LeadProductController@index` | List all products attached to a lead |
| `POST` | `/leads/{lead}/products` | `LeadProductController@store` | Attach a product to the lead |
| `PUT` | `/leads/{lead}/products/{product}` | `LeadProductController@update` | Update pivot data (quantity, notes) |
| `DELETE` | `/leads/{lead}/products/{product}` | `LeadProductController@destroy` | Detach a product from the lead |
| `POST` | `/leads/{lead}/products/sync` | `LeadProductController@sync` | Replace all attached products at once |

---

## Request / Response Shapes

### `POST /leads/{lead}/products`

**Request body:**

```json
{
  "product_id": 5,
  "quantity": 2,
  "notes": "Client interested in the enterprise tier"
}
```

| Field | Type | Rules |
|---|---|---|
| `product_id` | `integer` | required, exists in `products` |
| `quantity` | `integer` | optional, min `1`, default `1` |
| `notes` | `string` | nullable |

**Response `201`:**

```json
{ "message": "Product attached to lead successfully." }
```

---

### `PUT /leads/{lead}/products/{product}`

**Request body:**

```json
{
  "quantity": 3,
  "notes": "Updated to 3 licences"
}
```

| Field | Type | Rules |
|---|---|---|
| `quantity` | `integer` | optional, min `1` |
| `notes` | `string` | nullable |

**Response `200`:**

```json
{ "message": "Lead product updated successfully." }
```

---

### `DELETE /leads/{lead}/products/{product}`

No request body required.

**Response `200`:**

```json
{ "message": "Product detached from lead successfully." }
```

---

### `POST /leads/{lead}/products/sync`

Replaces **all** currently attached products with the provided list. Useful for bulk updates from a UI checklist.

**Request body:**

```json
{
  "products": [
    { "product_id": 1, "quantity": 1, "notes": null },
    { "product_id": 3, "quantity": 2, "notes": "Needs training bundle" }
  ]
}
```

| Field | Type | Rules |
|---|---|---|
| `products` | `array` | required |
| `products.*.product_id` | `integer` | required, exists in `products` |
| `products.*.quantity` | `integer` | optional, min `1`, default `1` |
| `products.*.notes` | `string` | nullable |

**Response `200`:**

```json
{ "message": "Lead products updated successfully." }
```

---

### `GET /leads/{lead}/products`

**Response `200`** — standard `ProductResource` collection:

```json
{
  "data": [
    {
      "id": 1,
      "name": "Enterprise CRM License",
      "sku": "CRM-ENT-001",
      "unit_price": "299.00",
      "currency": "USD",
      "is_active": true,
      "category": { "id": 2, "name": "Software" },
      ...
    }
  ]
}
```

---

### `LeadResource` — `products` field

When the `products` relation is eager-loaded, `LeadResource` includes:

```json
"products": [
  {
    "id": 1,
    "name": "Enterprise CRM License",
    "sku": "CRM-ENT-001",
    "unit_price": "299.00",
    "currency": "USD",
    "quantity": 2,
    "notes": "Client interested in the enterprise tier"
  }
]
```

The field is **omitted** when the relation is not loaded (e.g., in list responses).

---

## Authorization

| Action | Policy method | Notes |
|---|---|---|
| List products | `LeadPolicy@view` | `view-leads` permission |
| Attach / update / detach / sync | `LeadPolicy@update` | `manage-leads` permission |

`sales_rep` can manage products on leads assigned to them or created by them (same scope as the rest of the lead resource).

---

## Files

| File | Purpose |
|---|---|
| `database/migrations/2026_03_23_195518_create_lead_products_table.php` | Pivot table |
| `app/Models/Lead.php` | `products()` relationship added |
| `app/Models/Product.php` | `leads()` relationship added |
| `app/Http/Controllers/Api/LeadProductController.php` | index, store, update, destroy, sync |
| `app/Http/Requests/LeadProduct/StoreLeadProductRequest.php` | Validation for attach |
| `app/Http/Requests/LeadProduct/UpdateLeadProductRequest.php` | Validation for update pivot |
| `app/Http/Resources/Lead/LeadResource.php` | `products` field added (whenLoaded) |
| `lang/en/messages.php` | `lead_product_attached/updated/detached/synced` keys |
| `lang/ar/messages.php` | Arabic translations for the above keys |
