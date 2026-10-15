# 17 — File Attachments

Polymorphic file attachment system for attaching documents (proposals, contracts, invoices, etc.) to customers and opportunities.

Uses Laravel's **filesystem** (local/S3 driver configurable via `FILESYSTEM_DISK`).

---

## Database Table: `attachments`

| Column | Type | Constraints |
|---|---|---|
| `id` | `bigIncrements` | PK |
| `attachable_type` | `string` | required — polymorphic (Customer, Opportunity) |
| `attachable_id` | `unsignedBigInteger` | required — polymorphic |
| `uploaded_by` | `foreignId → users` | required, `constrained()->cascadeOnDelete()` |
| `original_name` | `string(255)` | required — original filename |
| `stored_name` | `string(255)` | required — hashed filename on disk |
| `mime_type` | `string(100)` | required |
| `size` | `unsignedBigInteger` | required — bytes |
| `disk` | `string(30)` | default `local` |
| `path` | `string(500)` | required — relative path on disk |
| `created_at` | `timestamp` | auto |
| `updated_at` | `timestamp` | auto |

**Indexes:** `(attachable_type, attachable_id)`, `(uploaded_by)`

---

## Storage Structure

Files are stored under `storage/app/attachments/{entity_type}/{entity_id}/`:

```
storage/app/attachments/
  leads/5/
    abc123.pdf
  customers/12/
    def456.docx
  organizations/3/
    ghi789.png
```

---

## Model

**File:** `app/Models/Attachment.php`

```php
class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'attachable_type', 'attachable_id', 'uploaded_by',
        'original_name', 'stored_name', 'mime_type',
        'size', 'disk', 'path',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    const ALLOWED_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg', 'image/png', 'image/gif',
        'text/plain', 'text/csv',
    ];

    const MAX_SIZE_MB = 10;

    // Relationships
    public function attachable(): MorphTo
    public function uploader(): BelongsTo  // → User (uploaded_by)

    // Helpers
    public function getFullPath(): string    // disk path for Storage facade
    public function getSizeForHumans(): string  // "2.5 MB"
}
```

### Trait: `HasAttachments`

**File:** `app/Models/Traits/HasAttachments.php`

Applied to: `Customer`, `Opportunity`.

```php
trait HasAttachments
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
```

---

## API Endpoints

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/{entity}/{id}/attachments` | `AttachmentController@index` | List attachments for an entity |
| `POST` | `/{entity}/{id}/attachments` | `AttachmentController@store` | Upload file(s) |
| `GET` | `/attachments/{attachment}` | `AttachmentController@show` | Get attachment metadata |
| `GET` | `/attachments/{attachment}/download` | `AttachmentController@download` | Download file |
| `DELETE` | `/attachments/{attachment}` | `AttachmentController@destroy` | Delete attachment + file |

Where `{entity}` is one of: `customers`, `opportunities`.

Example: `POST /api/v1/leads/5/attachments`

---

## Validation

### StoreAttachmentRequest

```php
public function rules(): array
{
    return [
        'files'   => ['required', 'array', 'max:5'],  // max 5 files per request
        'files.*' => [
            'required', 'file',
            'max:' . (Attachment::MAX_SIZE_MB * 1024),  // 10MB in KB
            'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,txt,csv',
        ],
    ];
}
```

---

## AttachmentResource

```json
{
  "id": 1,
  "original_name": "Proposal-AcmeCorp-v2.pdf",
  "mime_type": "application/pdf",
  "size": 2548000,
  "size_human": "2.43 MB",
  "download_url": "/api/v1/attachments/1/download",
  "uploaded_by": {
    "id": 2,
    "name": "Sales Rep"
  },
  "created_at": "2026-03-08T14:00:00Z"
}
```

---

## AttachmentService

**File:** `app/Services/AttachmentService.php`

| Method | Signature | Description |
|---|---|---|
| `list` | `list(Model $entity): Collection` | All attachments for an entity |
| `store` | `store(Model $entity, array $files, User $user): array` | Upload multiple files, return Attachment models |
| `download` | `download(Attachment $attachment): StreamedResponse` | Return file download response |
| `delete` | `delete(Attachment $attachment): void` | Delete file from disk + DB record |

### Business Rules

- `uploaded_by` set server-side from authenticated user.
- Files are stored with hashed names to avoid collisions.
- On delete, file is removed from disk before DB record is deleted.
- Entity type/ID validated — the entity must exist and the user must have permission to view it.
- Max 5 files per upload request to prevent abuse.

---

## AttachmentPolicy

| Method | Rule |
|---|---|
| `viewAny` | Resolved via the parent entity policy in the controller |
| `download` | Has `view-attachments` |
| `create` | Has `create-attachments` (plus `update` on the parent entity) |
| `delete` | Uploaded the file OR has `delete-attachments` |

---

## Frontend: AttachmentList + preview popup

`resources/js/components/shared/AttachmentList.vue` (used by the Customer and Opportunity detail Attachments tabs) renders a table (file/size/uploader/date) with per-row download, delete (gated by `canUpload`), and an eye-button preview for `application/pdf` and `image/*` (other types stay download-only).

The preview opens a `BaseModal` (`size="xl"`, scrollable): images render inline, PDFs in a 70vh iframe, both loaded through the session-cookie download URL. The footer holds an explicit Close button plus Download — the default modal-footer Close is replaced, so the footer Close must be kept. `FormFileUpload` labels its button `common.upload` ("Upload"/"رفع").

---

## Permissions

No new permissions needed — attachment access is derived from the parent entity's policy.

---

## Config

Add to `config/crm.php`:

```php
'attachments' => [
    'disk'          => env('ATTACHMENTS_DISK', 'local'),
    'max_size_mb'   => env('ATTACHMENTS_MAX_SIZE_MB', 10),
    'max_per_upload' => 5,
],
```
