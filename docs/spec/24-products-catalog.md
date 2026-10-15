# 24 — Products / Services Catalog

A catalog of products and services that can be attached to Opportunities as contract line items or open-pipeline interest. Categories are managed as a dedicated table to ensure consistency and support hierarchy.

## Terminology

The word "Product(s)" itself is company-configurable: Company Settings → Terminology stores `product_singular_en/plural_en/singular_ar/plural_ar` (see [30-company-settings.md](30-company-settings.md)). A services business can relabel the whole UI to Service(s)/خدمة/خدمات. Routes, permissions (`view-products`), tables, and API field names keep the `product` naming; only display strings change (frontend `useProductLabels()`, backend `CompanySettingsService::productLabels()`).

---

## Database

### `product_categories`

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint unsigned` | PK |
| `name` | `string` | Category name, unique |
| `slug` | `string` | URL-friendly, unique — auto-generated from name |
| `parent_id` | `foreignId` | Nullable → `product_categories` (self-referencing hierarchy) |
| `description` | `text` | Nullable |
| `sort_order` | `integer` | Default `0` — display order among siblings |
| `created_by` | `foreignId` | → `users` |
| `created_at` | `timestamp` | |
| `updated_at` | `timestamp` | |

**Unique constraint:** `name`

### `products`

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint unsigned` | PK |
| `name` | `string` | Product/service name |
| `sku` | `string` | Nullable, unique — stock-keeping unit or code |
| `description` | `text` | Nullable |
| `unit_price` | `decimal(12,2)` | Default price per unit |
| `currency` | `string(3)` | Default `USD` |
| `category_id` | `foreignId` | Nullable → `product_categories` |
| `tax_id` | `foreignId` | Nullable → `taxes` (`nullOnDelete`) — default tax applied to document lines |
| `is_active` | `boolean` | Default `true` — soft toggle |
| `created_by` | `foreignId` | → `users` |
| `created_at` | `timestamp` | |
| `updated_at` | `timestamp` | |

### `taxes`

Simple rate table. Products reference a tax; quotations/invoices snapshot the rate per line so historical documents survive later tax edits.

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint unsigned` | PK |
| `name` | `string(100)` | Unique (e.g. `VAT 14`) |
| `percent` | `decimal(5,2)` | `0–100` |
| `is_active` | `boolean` | Default `true` — inactive taxes cannot be attached to new products |
| `created_by` | `foreignId` | → `users` |
| `created_at` / `updated_at` | `timestamp` | |

Delete is blocked (`messages.tax_delete_has_products`) while products reference the tax.

### `opportunity_products` (pivot)

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint unsigned` | PK |
| `opportunity_id` | `foreignId` | → `opportunities` |
| `product_id` | `foreignId` | → `products` |
| `quantity` | `integer` | Default `1`, min `1` |
| `unit_price` | `decimal(12,2)` | Nullable custom price; defaults to catalog price |
| `discount_percent` | `decimal(5,2)` | Nullable — when present it wins and derives `discount_amount` |
| `discount_amount` | `decimal(12,2)` | Default `0`, capped at line gross |
| `notes` | `text` | Nullable — line-item notes |
| `created_at` | `timestamp` | |
| `updated_at` | `timestamp` | |

Unique constraint: (`opportunity_id`, `product_id`). Lines are returned in insertion order (`orderByPivot('id')`). Discounts are entered **only** on opportunity lines; quotations/invoices derive and snapshot them.

Unique constraint: (`opportunity_id`, `product_id`). Product quantities on won opportunities contribute to booked sales; quantities on open opportunities represent pipeline interest.

---

## Models

### ProductCategory

```php
class ProductCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'parent_id', 'description', 'sort_order', 'created_by',
    ];

    // Relationships
    public function parent(): BelongsTo        // → ProductCategory (parent_id)
    public function children(): HasMany        // → ProductCategory (parent_id)
    public function products(): HasMany        // → Product
    public function creator(): BelongsTo       // → User

    // Auto-generate slug from name on create
    protected static function booted(): void
    {
        static::creating(function (ProductCategory $cat) {
            $cat->slug ??= Str::slug($cat->name);
        });
    }

    // Scopes
    public function scopeRoots(Builder $q)     // where parent_id IS NULL
    public function scopeOrdered(Builder $q)   // orderBy sort_order
}
```

### Product

```php
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'sku', 'description', 'unit_price',
        'currency', 'category_id', 'tax_id', 'is_active', 'created_by',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'is_active'  => 'boolean',
    ];

    // Relationships
    public function creator(): BelongsTo       // → User
    public function category(): BelongsTo      // → ProductCategory
    public function tax(): BelongsTo           // → Tax (nullable)
    public function opportunities(): BelongsToMany // → Opportunity through opportunity_products

    // Scopes
    public function scopeActive(Builder $q)
    public function scopeSearch(Builder $q, string $term)  // name, sku LIKE
    public function scopeInCategory(Builder $q, int $categoryId)  // includes children
}
```

### Tax

```php
class Tax extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'percent', 'is_active', 'created_by'];

    protected $casts = ['percent' => 'decimal:2', 'is_active' => 'boolean'];

    // Relationships
    public function creator(): BelongsTo  // → User
    public function products(): HasMany   // → Product
}
```

Inactive taxes are rejected on product create/update (`ProductService::ensureActiveTax()`); existing document snapshots keep working.

### Opportunity products

```php
class Opportunity
{
    public function products(): BelongsToMany // → Product through opportunity_products, orderByPivot('id')
}
```

Lines are always returned in insertion order, so the Products tab and quotation/invoice snapshots share one stable order.

---

## Validation

### StoreProductCategoryRequest

```php
'name'        => 'required|string|max:100|unique:product_categories,name',
'parent_id'   => 'nullable|exists:product_categories,id',
'description' => 'nullable|string|max:500',
'sort_order'  => 'integer|min:0',
```

### UpdateProductCategoryRequest

Same as store; `name` unique rule ignores current category. Cannot set `parent_id` to own ID or a descendant (prevent circular hierarchy).

### StoreProductRequest

```php
'name'        => 'required|string|max:255',
'sku'         => 'nullable|string|max:50|unique:products,sku',
'description' => 'nullable|string|max:2000',
'unit_price'  => 'required|numeric|min:0',
'currency'    => 'nullable|string|size:3',
'category_id' => 'nullable|exists:product_categories,id',
'tax_id'      => 'nullable|exists:taxes,id',
'is_active'   => 'boolean',
```

An inactive `tax_id` is rejected by `ProductService` with a `tax_id` validation error.

### UpdateProductRequest

Same as store; `sku` unique rule ignores current product. `tax_id` uses `sometimes`.

### StoreTaxRequest / UpdateTaxRequest

```php
'name'    => 'required|string|max:100|unique:taxes,name',
'percent' => 'required|numeric|min:0|max:100',
'is_active' => 'sometimes|boolean',
```

Update rules are `sometimes`; `name` unique ignores the current tax.

### StoreOpportunityProductRequest

```php
'product_id' => ['required', 'integer', 'exists:products,id'],
'quantity' => ['sometimes', 'integer', 'min:1'],
'unit_price' => ['nullable', 'numeric', 'min:0'],
'discount_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
'discount_amount' => ['sometimes', 'numeric', 'min:0'],
'notes' => ['nullable', 'string'],
```

### UpdateOpportunityProductRequest

`quantity`, `unit_price`, `discount_percent`, `discount_amount`, and `notes` use the same rules as Store; all fields are optional. Percent wins when present (amount derived); otherwise the amount is capped at line gross. Changing quantity/price recomputes a percent-based amount; amount-only lines keep their amount (capped to the new gross).

### Opportunity product sync

The controller validates `products` as a required array. Each item requires `product_id` and may include `quantity`, `unit_price`, `discount_percent`, `discount_amount`, and `notes`.

---

## API Resources

### ProductCategoryResource

```json
{
    "id": 1,
    "name": "Software",
    "slug": "software",
    "parent_id": null,
    "description": "Software products and licenses",
    "sort_order": 0,
    "children": [
        { "id": 3, "name": "SaaS", "slug": "saas", "parent_id": 1, "children": [] },
        { "id": 4, "name": "On-Premise", "slug": "on-premise", "parent_id": 1, "children": [] }
    ]
}
```

> `children` is included on `show` and the full tree endpoint. `index` returns a flat list without children.

### ProductResource

```json
{
    "id": 1,
    "name": "CRM Pro License",
    "sku": "CRM-PRO-001",
    "description": "Annual CRM Pro subscription",
    "unit_price": "499.00",
    "currency": "USD",
    "category": { "id": 3, "name": "SaaS", "slug": "saas" },
    "tax_id": 2,
    "tax": { "id": 2, "name": "VAT 14", "percent": "14.00" },
    "is_active": true,
    "created_at": "..."
}
```

### TaxResource

```json
{
    "id": 2,
    "name": "VAT 14",
    "percent": "14.00",
    "is_active": true,
    "products_count": 5,
    "created_at": "..."
}
```

`products_count` is included via `loadCount` on `show` only.

### Opportunity product entry

```json
{
    "id": 1,
    "product": { "id": 1, "name": "CRM Pro License", "sku": "CRM-PRO-001" },
    "quantity": 5,
    "unit_price": "499.00",
    "discount_percent": "10.00",
    "discount_amount": "249.50",
    "notes": null
}
```

---

## Services

### ProductCategoryService

```php
class ProductCategoryService
{
    public function tree(): Collection         // nested tree (roots with children eager-loaded)
    public function list(): Collection         // flat list ordered by sort_order
    public function create(array $data): ProductCategory
    public function update(ProductCategory $cat, array $data): ProductCategory
    public function delete(ProductCategory $cat): void  // blocks if products exist under this category
    public function reorder(array $orderedIds): void    // update sort_order in bulk
}
```

### ProductService

```php
class ProductService
{
    public function list(array $filters): LengthAwarePaginator
    public function create(array $data): Product
    public function update(Product $product, array $data): Product
    public function delete(Product $product): void  // soft toggle is_active = false
}
```

### TaxService

```php
class TaxService
{
    public function list(bool $activeOnly = false): Collection  // ordered by name
    public function create(array $data): Tax
    public function update(Tax $tax, array $data): Tax
    public function delete(Tax $tax): void  // blocks with messages.tax_delete_has_products while products reference it
}
```

Opportunity pivot operations are handled directly by `OpportunityProductController`; there is no separate product-line service.

---

## Controller

### ProductCategoryController

```php
class ProductCategoryController extends Controller
{
    public function index()                                   // GET /product-categories (flat list)
    public function tree()                                    // GET /product-categories/tree (nested)
    public function store(StoreProductCategoryRequest $r)     // POST /product-categories
    public function show(ProductCategory $productCategory)    // GET /product-categories/{category}
    public function update(UpdateProductCategoryRequest $r, ProductCategory $productCategory)  // PUT
    public function destroy(ProductCategory $productCategory) // DELETE
    public function reorder(Request $r)                       // POST /product-categories/reorder
}
```

### ProductController

```php
class ProductController extends Controller
{
    public function index(Request $request)        // GET /products
    public function store(StoreProductRequest $r)   // POST /products
    public function show(Product $product)          // GET /products/{product}
    public function update(UpdateProductRequest $r, Product $product)  // PUT
    public function destroy(Product $product)       // DELETE (soft toggle)
}
```

### TaxController

```php
class TaxController extends Controller
{
    public function index(Request $request)              // GET /taxes (?active_only=1)
    public function store(StoreTaxRequest $r)            // POST /taxes (201)
    public function show(Tax $tax)                       // GET /taxes/{tax}
    public function update(UpdateTaxRequest $r, Tax $tax) // PUT /taxes/{tax}
    public function destroy(Tax $tax)                    // DELETE /taxes/{tax} (204)
}
```

### OpportunityProductController

```php
class OpportunityProductController extends Controller
{
    public function index(Customer $customer, Opportunity $opportunity)
    public function store(StoreOpportunityProductRequest $request, Customer $customer, Opportunity $opportunity)
    public function update(UpdateOpportunityProductRequest $request, Customer $customer, Opportunity $opportunity, Product $product)
    public function destroy(Customer $customer, Opportunity $opportunity, Product $product)
    public function sync(Customer $customer, Opportunity $opportunity)
}
```

Discount resolution (`resolveDiscount()`): percent wins when present and derived into the amount; otherwise the amount is capped at line gross. Updating quantity/price recomputes percent-based amounts.

---

## Endpoints

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

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/products` | `ProductController@index` | List products (filter by category, search) |
| `POST` | `/products` | `ProductController@store` | Create product |
| `GET` | `/products/{product}` | `ProductController@show` | Show product |
| `PUT` | `/products/{product}` | `ProductController@update` | Update product |
| `DELETE` | `/products/{product}` | `ProductController@destroy` | Deactivate product |
| `GET` | `/product-categories/{category}/products` | `ProductController@byCategory` | Products in a category (includes descendants) |
| `GET` | `/customers/{customer}/opportunities/{opportunity}/products` | `OpportunityProductController@index` | List attached products (insertion order) |
| `POST` | `/customers/{customer}/opportunities/{opportunity}/products` | `OpportunityProductController@store` | Attach a product |
| `PUT` | `/customers/{customer}/opportunities/{opportunity}/products/{product}` | `OpportunityProductController@update` | Update quantity, price, discount, or notes |
| `DELETE` | `/customers/{customer}/opportunities/{opportunity}/products/{product}` | `OpportunityProductController@destroy` | Detach a product |
| `POST` | `/customers/{customer}/opportunities/{opportunity}/products/sync` | `OpportunityProductController@sync` | Replace all attached products |

### Taxes

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/taxes` | `TaxController@index` | List taxes (`?active_only=1` for product pickers) |
| `POST` | `/taxes` | `TaxController@store` | Create tax |
| `GET` | `/taxes/{tax}` | `TaxController@show` | Show tax + `products_count` |
| `PUT` | `/taxes/{tax}` | `TaxController@update` | Update tax |
| `DELETE` | `/taxes/{tax}` | `TaxController@destroy` | Delete (blocked while products use it) |

---

## Line-based document math

Quotations and invoices derive all totals from opportunity lines via `DocumentTotals::line()/headers()` — no header-level tax/discount input exists. Per line: `gross = qty × price`; percent (when present) derives `amount = round(gross × pct/100, 2)`, else the amount is capped at gross; `tax = round((gross − amount) × rate/100, 2)`. Headers: `subtotal = Σ gross`, `discount_amount = Σ amounts`, `tax_amount = Σ taxes`, `total = subtotal − discount + tax`. Items snapshot `tax_id/name/percent` and `discount_percent/amount`, so documents survive later tax or product edits. `tax_percent` on the quotation/invoice header is legacy and stored as `0`.

---

## Permissions

| Permission | Admin | Manager | Sales Rep |
|---|---|---|---|
| `create/update/delete-products` | ✅ | ✅ | ❌ |
| `view-products` | ✅ | ✅ | ✅ |
| `create/update/delete-taxes` | ✅ | ✅ | ❌ |
| `view-taxes` | ✅ | ✅ | ✅ |

Category and tax management require the respective create/update/delete permissions. All roles with `view-products` can read categories and (with `view-taxes`) taxes.

Opportunity product management follows Opportunity authorization; users able to update an Opportunity can manage its product lines.

---

## Policy

### ProductCategoryPolicy

| Method | Rule |
|---|---|
| `viewAny` | Authenticated |
| `view` | Authenticated |
| `create` | Has `create-products` |
| `update` | Has `update-products` |
| `delete` | Has `delete-products` AND no products assigned to this category |

### ProductPolicy

| Method | Rule |
|---|---|
| `viewAny` | Has `view-products` |
| `view` | Has `view-products` |
| `create` | Has `create-products` |
| `update` | Has `update-products` |
| `delete` | Has `delete-products` |

### TaxPolicy

| Method | Rule |
|---|---|
| `viewAny` | Has `view-taxes` |
| `view` | Has `view-taxes` |
| `create` | Has `create-taxes` |
| `update` | Has `update-taxes` |
| `delete` | Has `delete-taxes` (service additionally blocks when products reference the tax) |
