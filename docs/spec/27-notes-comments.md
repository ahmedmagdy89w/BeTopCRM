# 27 — Notes / Comments

Universal polymorphic notes system for any CRM entity. Provides threaded comments with @mentions.

---

## Database

### `notes`

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint unsigned` | PK |
| `notable_type` | `string` | Morph type (`Customer`, `Opportunity`) |
| `notable_id` | `bigint unsigned` | Morph ID |
| `parent_id` | `foreignId` | Nullable → `notes` (self-referencing for replies) |
| `user_id` | `foreignId` | → `users` (author) |
| `body` | `text` | Note content (plain text or markdown) |
| `is_pinned` | `boolean` | Default `false` — pinned notes appear first |
| `created_at` | `timestamp` | |
| `updated_at` | `timestamp` | |
| `deleted_at` | `timestamp` | Soft delete |

**Indexes:** `(notable_type, notable_id)`, `(parent_id)`

---

## Model

### Note

```php
class Note extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'notable_type', 'notable_id', 'parent_id',
        'user_id', 'body', 'is_pinned',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
    ];

    // Relationships
    public function notable(): MorphTo             // → polymorphic parent
    public function author(): BelongsTo            // → User (user_id)
    public function parent(): BelongsTo            // → Note (parent_id)
    public function replies(): HasMany             // → Note (parent_id)

    // Scopes
    public function scopeTopLevel(Builder $q)      // where parent_id IS NULL
    public function scopePinned(Builder $q)
}
```

### HasNotes trait

Applied to Customer and Opportunity:

```php
trait HasNotes
{
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }

    public function pinnedNotes(): MorphMany
    {
        return $this->notes()->where('is_pinned', true);
    }
}
```

---

## @Mentions

When `body` contains `@username` patterns, the service extracts mentioned users and dispatches `MentionedInNoteNotification`.

```php
// Extract mentions
preg_match_all('/@(\w+)/', $body, $matches);
$mentionedUsers = User::whereIn('name', $matches[1])->get();
```

---

## Validation

### StoreNoteRequest

```php
'body'      => 'required|string|max:10000',
'parent_id' => 'nullable|exists:notes,id',
'is_pinned' => 'boolean',
```

### UpdateNoteRequest

```php
'body'      => 'required|string|max:10000',
'is_pinned' => 'boolean',
```

---

## API Resource

### NoteResource

```json
{
    "id": 1,
    "body": "Spoke with client about renewal. @jane please follow up.",
    "is_pinned": false,
    "author": { "id": 5, "name": "Ahmed" },
    "parent_id": null,
    "replies_count": 2,
    "replies": [
        {
            "id": 2,
            "body": "Will do, scheduling call for Friday.",
            "author": { "id": 8, "name": "Jane" },
            "parent_id": 1,
            "replies_count": 0,
            "created_at": "..."
        }
    ],
    "created_at": "...",
    "updated_at": "..."
}
```

Replies are nested one level deep. Deeper nesting returns `replies_count` only (flat threading).

---

## Service

### NoteService

```php
class NoteService
{
    public function listForEntity(Model $entity, array $filters): LengthAwarePaginator
    {
        // Returns top-level notes with eager-loaded replies (1 level)
        // Pinned notes first, then by created_at desc
    }

    public function create(Model $entity, array $data, User $user): Note
    {
        // Create note
        // Extract @mentions → dispatch MentionedInNoteNotification
        // Return note with author loaded
    }

    public function update(Note $note, array $data): Note
    public function delete(Note $note): void   // soft delete
    public function togglePin(Note $note): Note
}
```

---

## Controller

### NoteController

```php
class NoteController extends Controller
{
    public function index(string $entityType, int $entityId)   // GET /{entity}/{id}/notes
    public function store(StoreNoteRequest $r, string $entityType, int $entityId)  // POST
    public function update(UpdateNoteRequest $r, Note $note)   // PUT /notes/{note}
    public function destroy(Note $note)                        // DELETE /notes/{note}
    public function togglePin(Note $note)                      // POST /notes/{note}/toggle-pin
}
```

---

## Endpoints

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/{entity}/{id}/notes` | `NoteController@index` | List entity's notes (threaded) |
| `POST` | `/{entity}/{id}/notes` | `NoteController@store` | Add note to entity |
| `PUT` | `/notes/{note}` | `NoteController@update` | Edit note (author only) |
| `DELETE` | `/notes/{note}` | `NoteController@destroy` | Soft-delete note |
| `POST` | `/notes/{note}/toggle-pin` | `NoteController@togglePin` | Pin/unpin note |

> `{entity}` = `customers`, `opportunities`

---

## Notification

### MentionedInNoteNotification

```php
class MentionedInNoteNotification extends Notification
{
    // "Ahmed mentioned you in a note on Lead #42: 'Spoke with client...'"
    // via: ['database']
}
```

---

## Permissions

No dedicated permission — access follows the parent entity:

- Can view entity → can view its notes
- Can view entity → can create notes on it
- Note author → can edit/delete own notes
- Has manage permission on entity → can delete any note, can pin/unpin

---

## Policy

### NotePolicy

| Method | Rule |
|---|---|
| `view` | Can view the parent entity |
| `create` | Can view the parent entity |
| `update` | Is the note author |
| `delete` | Is the note author OR has manage permission on parent entity |
| `togglePin` | Has manage permission on parent entity |
