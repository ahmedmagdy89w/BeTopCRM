# 18 — Import / Export

CSV import for onboarding existing data and streamed CSV export for reporting and backup.

Imports run as **queued jobs** to handle large files without blocking. Exports use **streaming** for memory efficiency.

---

## Database Table: `imports`

Tracks import job status and results.

| Column | Type | Constraints |
|---|---|---|
| `id` | `bigIncrements` | PK |
| `user_id` | `foreignId → users` | required, `constrained()->cascadeOnDelete()` |
| `entity_type` | `string(30)` | required — `leads`, `customers`, `opportunities`, `products`, `categories` |
| `original_filename` | `string(255)` | required |
| `status` | `string(20)` | default `pending` |
| `total_rows` | `unsignedInteger` | nullable — set after parsing |
| `processed_rows` | `unsignedInteger` | default `0` |
| `successful_rows` | `unsignedInteger` | default `0` |
| `failed_rows` | `unsignedInteger` | default `0` |
| `errors` | `json` | nullable — array of `{row, field, message}` |
| `completed_at` | `datetime` | nullable |
| `created_at` | `timestamp` | auto |
| `updated_at` | `timestamp` | auto |

### Import Status Values

`pending`, `processing`, `completed`, `failed`

---

## Model

**File:** `app/Models/Import.php`

```php
class Import extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'entity_type', 'original_filename', 'status',
        'total_rows', 'processed_rows', 'successful_rows', 'failed_rows',
        'errors', 'completed_at',
    ];

    protected $casts = [
        'errors'       => 'array',
        'completed_at' => 'datetime',
    ];

    const STATUSES      = ['pending', 'processing', 'completed', 'failed'];
    const ENTITY_TYPES  = ['leads', 'customers', 'opportunities', 'products', 'categories'];

    // Relationships
    public function user(): BelongsTo  // → User
}
```

---

## API Endpoints

### Import

| Method | URI | Action | Description |
|---|---|---|---|
| `POST` | `/import/{entity_type}` | `ImportController@store` | Upload CSV and start import |
| `GET` | `/import/{import}` | `ImportController@show` | Check import status/progress |
| `GET` | `/import/{entity_type}/template` | `ImportController@template` | Download blank CSV template |
| `GET` | `/import` | `ImportController@index` | List my imports (recent) |

Where `{entity_type}` is `leads`, `customers`, `opportunities`, `products`, or `categories`.

### Export

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/export/leads` | `ExportController@leads` | Export leads as CSV |
| `GET` | `/export/customers` | `ExportController@customers` | Export customers as CSV |
| `GET` | `/export/opportunities` | `ExportController@opportunities` | Export opportunities as CSV |
| `GET` | `/export/products` | `ExportController@products` | Export products as CSV |
| `GET` | `/export/categories` | `ExportController@categories` | Export product categories as CSV |

Export endpoints accept the applicable list filters. Product exports include inactive products by default; `is_active=true` or `is_active=false` selects a subset. Category export includes the full hierarchy, with parents emitted before children.

---

## CSV Template Columns

### Leads

```csv
type,first_name,last_name,email,phone,company_name,industry,website,source,lead_status,estimated_value,description,notes
```

### Customers

```csv
type,first_name,last_name,email,phone,company_name,industry,website,source,status,contract_value,description,notes
```

Customer imports create non-lead customer records (`is_lead=false`). `type` is `individual` or `company`; a company needs `company_name`, while an individual needs `first_name` and `last_name`.

### Products

```csv
name,sku,description,unit_price,currency,category_slug,is_active
```

`category_slug` is optional and must match an existing product category slug. An empty SKU is allowed; a non-empty SKU must be unique.

### Product Categories

```csv
name,slug,parent_slug,description,sort_order
```

`slug` may be omitted and is generated from `name`. `parent_slug` is optional and must match an existing category. Import parent categories before their children. Category exports use this same parent-first order.

---

## Validation

### ImportRequest

```php
public function rules(): array
{
    return [
        'file' => [
            'required', 'file',
            'mimes:csv,txt',
            'max:5120',  // 5MB max
        ],
    ];
}
```

### Export query parameters

Exports reuse the supported filter parameters of their entity list endpoints. Exports are CSV; there is no `format` or `columns` parameter.

---

## ImportResource

```json
{
  "id": 1,
  "entity_type": "leads",
  "original_filename": "leads-march-2026.csv",
  "status": "completed",
  "total_rows": 150,
  "processed_rows": 150,
  "successful_rows": 142,
  "failed_rows": 8,
  "errors": [
    { "row": 12, "field": "email", "message": "The email has already been taken." },
    { "row": 45, "field": "source", "message": "Invalid source value." }
  ],
  "completed_at": "2026-03-08T14:05:00Z",
  "created_at": "2026-03-08T14:00:00Z"
}
```

---

## ImportService

**File:** `app/Services/ImportService.php`

| Method | Signature | Description |
|---|---|---|
| `start` | `start(string $entityType, UploadedFile $file, User $user): Import` | Store file, create Import record, dispatch job |
| `show` | `show(Import $import): Import` | Return import with progress |
| `list` | `list(User $user): Collection` | Recent imports for user |
| `template` | `template(string $entityType): StreamedResponse` | Stream blank CSV template |

## ExportService

**File:** `app/Services/ExportService.php`

| Method | Signature | Description |
|---|---|---|
| `leads` | `leads(array $filters, User $user): StreamedResponse` | Stream leads CSV |
| `customers` | `customers(array $filters, User $user): StreamedResponse` | Stream customers CSV |
| `opportunities` | `opportunities(array $filters, User $user): StreamedResponse` | Stream opportunities CSV |
| `products` | `products(array $filters, User $user): StreamedResponse` | Stream products CSV, including inactive products by default |
| `categories` | `categories(array $filters, User $user): StreamedResponse` | Stream categories CSV in parent-first order |

### Business Rules

- Exports use `LazyCollection` / `cursor()` — never loads all records into memory.
- CSV responses set `Content-Type: text/csv` and `Content-Disposition: attachment`.
- Import files are stored temporarily in `storage/app/imports/` and deleted after processing.
- Each CSV row is validated against the corresponding entity's store constraints. The import owner is assigned server-side as `created_by`.
- Failed rows are skipped (not rolled back) — import continues and reports errors per row.
- Duplicate detection: leads/customers match by email when present, products by SKU when present, and categories by name or slug.
- On duplicate: skip the row and report an error (no overwrite by default).
- Product/category relationships use category slugs rather than database-specific IDs. A missing category or parent slug is reported on that row.
- CSV parsing follows quoted records, including embedded commas and newlines. Rows whose field count differs from the header are skipped and reported without stopping later rows.

---

## Queued Job

**File:** `app/Jobs/ProcessImportJob.php`

```php
class ProcessImportJob implements ShouldQueue
{
    public int $tries = 1;
    public int $timeout = 300;  // 5 minutes max

    public function handle(): void
    {
        // 1. Set import status to 'processing'
        // 2. Parse CSV row by row
        // 3. Validate each row
        // 4. Create record or collect error
        // 5. Update processed_rows count periodically
        // 6. Set status to 'completed', record errors
    }

    public function failed(\Throwable $e): void
    {
        // Set import status to 'failed'
    }
}
```

---

## Permissions

| Permission | Admin | Manager | Sales Rep |
|---|---|---|---|
| `import-data` | ✅ | ✅ | ❌ |
| `export-data` | ✅ | ✅ | ✅ (own data only) |

Sales reps can export leads assigned to them and customers they manage, but cannot import.
