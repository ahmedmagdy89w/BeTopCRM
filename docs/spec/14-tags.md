# 14 — Tags

Polymorphic tagging system for flexible categorization of leads, customers, and organizations.

---

## Database Tables

### `tags`

| Column | Type | Constraints |
|---|---|---|
| `id` | `bigIncrements` | PK |
| `name` | `string(50)` | required, unique |
| `color` | `string(7)` | nullable — hex color (e.g., `#FF5733`) |
| `created_by` | `foreignId → users` | required, `constrained()->cascadeOnDelete()` |
| `created_at` | `timestamp` | auto |
| `updated_at` | `timestamp` | auto |

### `taggables` (pivot)

| Column | Type | Constraints |
|---|---|---|
| `tag_id` | `foreignId → tags` | `constrained()->cascadeOnDelete()` |
| `taggable_type` | `string` | polymorphic — `Lead`, `Customer`, `Organization` |
| `taggable_id` | `unsignedBigInteger` | polymorphic |

**Primary key:** `(tag_id, taggable_type, taggable_id)` — prevents duplicates.

---

## Model

**File:** `app/Models/Tag.php`

```php
class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'color', 'created_by'];

    // Relationships
    public function creator(): BelongsTo  // → User
    public function leads(): MorphToMany  // → Lead via taggables
    public function customers(): MorphToMany  // → Customer via taggables
    public function organizations(): MorphToMany  // → Organization via taggables
}
```

### Trait: `HasTags`

**File:** `app/Models/Traits/HasTags.php`

Applied to `Lead`, `Customer`, `Organization` models.

```php
trait HasTags
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function scopeWithTag(Builder $q, string|int $tag): Builder
    {
        return $q->whereHas('tags', fn ($q) =>
            is_numeric($tag) ? $q->where('tags.id', $tag) : $q->where('tags.name', $tag)
        );
    }

    public function syncTags(array $tagIds): void
    {
        $this->tags()->sync($tagIds);
    }
}
```

---

## API Endpoints

### Tag CRUD

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/tags` | `TagController@index` | List all tags |
| `POST` | `/tags` | `TagController@store` | Create a tag |
| `PUT` | `/tags/{tag}` | `TagController@update` | Update tag name/color |
| `DELETE` | `/tags/{tag}` | `TagController@destroy` | Delete tag (detaches from all entities) |

### Attach/Detach Tags

| Method | URI | Action | Description |
|---|---|---|---|
| `POST` | `/leads/{lead}/tags` | `LeadController@syncTags` | Sync tags for a lead |
| `POST` | `/customers/{customer}/tags` | `CustomerController@syncTags` | Sync tags for a customer |
| `POST` | `/organizations/{organization}/tags` | `OrganizationController@syncTags` | Sync tags for an org |

The sync endpoint accepts `{ "tag_ids": [1, 3, 5] }` and replaces the entity's tags.

---

## Validation

### StoreTagRequest

```php
public function rules(): array
{
    return [
        'name'  => ['required', 'string', 'max:50', 'unique:tags,name'],
        'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
    ];
}
```

### UpdateTagRequest

```php
public function rules(): array
{
    return [
        'name'  => ['sometimes', 'string', 'max:50', Rule::unique('tags')->ignore($this->tag)],
        'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
    ];
}
```

### SyncTagsRequest

```php
public function rules(): array
{
    return [
        'tag_ids'   => ['required', 'array'],
        'tag_ids.*' => ['integer', 'exists:tags,id'],
    ];
}
```

---

## TagResource

```json
{
  "id": 1,
  "name": "VIP",
  "color": "#FF5733",
  "created_at": "2026-03-08T10:00:00Z"
}
```

Tags are included as a nested array in `LeadResource`, `CustomerResource`, and `OrganizationResource`:

```json
"tags": [
  { "id": 1, "name": "VIP", "color": "#FF5733" },
  { "id": 3, "name": "Enterprise", "color": "#3498DB" }
]
```

---

## TagService

**File:** `app/Services/TagService.php`

| Method | Signature | Description |
|---|---|---|
| `list` | `list(): Collection` | All tags (not paginated — typically small set) |
| `create` | `create(array $data, User $user): Tag` | Create with `created_by` |
| `update` | `update(Tag $tag, array $data): Tag` | Update name/color |
| `delete` | `delete(Tag $tag): void` | Delete tag (cascade removes pivot rows) |

---

## Filter Integration

Add `tag` query parameter to existing filters:

| Endpoint | New Param | Description |
|---|---|---|
| `GET /leads` | `tag` | Filter leads by tag name or ID |
| `GET /customers` | `tag` | Filter customers by tag name or ID |
| `GET /organizations` | `tag` | Filter organizations by tag name or ID |

---

## TagPolicy

| Method | Rule |
|---|---|
| `viewAny` | Authenticated |
| `create` | Has `manage-tags` |
| `update` | Has `manage-tags` |
| `delete` | Has `manage-tags` |

Syncing tags on an entity requires the ability to update that entity (delegated to the entity's own policy).
