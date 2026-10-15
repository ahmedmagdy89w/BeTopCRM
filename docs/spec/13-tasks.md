# 13 — Tasks

A general-purpose task / to-do system that can be linked to any CRM entity (lead, customer, organization) or stand alone as a personal reminder.

---

## Database Table: `tasks`

| Column | Type | Constraints |
|---|---|---|
| `id` | `bigIncrements` | PK |
| `user_id` | `foreignId → users` | required — assigned user, `constrained()->cascadeOnDelete()` |
| `taskable_type` | `string` | nullable — polymorphic (Lead, Customer, Organization) |
| `taskable_id` | `unsignedBigInteger` | nullable — polymorphic |
| `title` | `string(255)` | required |
| `description` | `text` | nullable |
| `priority` | `string(20)` | default `medium` |
| `status` | `string(20)` | default `pending` |
| `due_date` | `datetime` | nullable |
| `completed_at` | `datetime` | nullable |
| `created_by` | `foreignId → users` | required, `constrained()->cascadeOnDelete()` |
| `created_at` | `timestamp` | auto |
| `updated_at` | `timestamp` | auto |

**Indexes:** `(user_id, status)`, `(due_date)`, `(taskable_type, taskable_id)`

### Priority Values

`low`, `medium`, `high`, `urgent`

### Status Values

`pending`, `in_progress`, `completed`, `cancelled`

---

## Model

**File:** `app/Models/Task.php`

```php
class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'taskable_type', 'taskable_id',
        'title', 'description', 'priority', 'status',
        'due_date', 'completed_at', 'created_by',
    ];

    protected $casts = [
        'due_date'     => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'priority' => 'medium',
        'status'   => 'pending',
    ];

    const PRIORITIES = ['low', 'medium', 'high', 'urgent'];
    const STATUSES   = ['pending', 'in_progress', 'completed', 'cancelled'];

    // Relationships
    public function assignee(): BelongsTo    // → User (user_id)
    public function creator(): BelongsTo     // → User (created_by)
    public function taskable(): MorphTo      // → Lead, Customer, Organization, or null

    // Scopes
    public function scopeForUser(Builder $q, int $userId)
    public function scopeStatus(Builder $q, string $status)
    public function scopePriority(Builder $q, string $priority)
    public function scopeOverdue(Builder $q)              // due_date < now, not completed
    public function scopeDueToday(Builder $q)              // due_date is today
    public function scopeUpcoming(Builder $q, int $days)   // due within N days

    // Helpers
    public function isOverdue(): bool
    public function markCompleted(): void   // sets status=completed, completed_at=now()
}
```

### User Model Addition

```php
public function tasks(): HasMany           // → Task (user_id) — tasks assigned to me
public function createdTasks(): HasMany    // → Task (created_by)
```

---

## API Endpoints

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/tasks` | `TaskController@index` | List tasks (filtered by user, status, priority, entity) |
| `POST` | `/tasks` | `TaskController@store` | Create a task |
| `GET` | `/tasks/{task}` | `TaskController@show` | Show task detail |
| `PUT` | `/tasks/{task}` | `TaskController@update` | Update task |
| `DELETE` | `/tasks/{task}` | `TaskController@destroy` | Delete task |
| `PATCH` | `/tasks/{task}/complete` | `TaskController@complete` | Mark task completed |
| `GET` | `/tasks/my-summary` | `TaskController@mySummary` | Dashboard: my overdue, today, upcoming counts |

### Query Parameters (index)

| Param | Type | Description |
|---|---|---|
| `user_id` | `int` | Filter by assigned user (admins/managers can see others) |
| `status` | `string` | Filter by status |
| `priority` | `string` | Filter by priority |
| `entity_type` | `string` | Filter by linked entity type (`lead`, `customer`, `organization`) |
| `entity_id` | `int` | Filter by linked entity ID (requires `entity_type`) |
| `overdue` | `bool` | If true, only overdue tasks |
| `due_today` | `bool` | If true, only tasks due today |
| `sort_by` | `string` | Column to sort (default: `due_date`) |
| `sort_dir` | `string` | `asc` or `desc` (default: `asc`) |
| `per_page` | `int` | Items per page (default: 15, max: 100) |

---

## Validation

### StoreTaskRequest

```php
public function rules(): array
{
    return [
        'user_id'       => ['sometimes', 'exists:users,id'],  // defaults to auth user
        'title'         => ['required', 'string', 'max:255'],
        'description'   => ['nullable', 'string'],
        'priority'      => ['sometimes', 'string', Rule::in(Task::PRIORITIES)],
        'due_date'      => ['nullable', 'date'],
        'entity_type'   => ['nullable', 'string', Rule::in(['lead', 'customer', 'organization'])],
        'entity_id'     => ['required_with:entity_type', 'integer'],
    ];
}
```

### UpdateTaskRequest

Same as Store but all fields `sometimes`. Also allows `status` to be set.

---

## TaskResource

```json
{
  "id": 1,
  "title": "Prepare proposal for Acme",
  "description": "Include pricing tiers and timeline",
  "priority": "high",
  "status": "pending",
  "due_date": "2026-03-10T09:00:00Z",
  "completed_at": null,
  "is_overdue": false,
  "assignee": {
    "id": 2,
    "name": "Sales Rep"
  },
  "created_by": {
    "id": 1,
    "name": "Admin"
  },
  "entity": {
    "type": "lead",
    "id": 5,
    "name": "Acme Corp Opportunity"
  },
  "created_at": "2026-03-08T10:00:00Z",
  "updated_at": "2026-03-08T10:00:00Z"
}
```

The `entity` block is resolved from the polymorphic relation — shows `type`, `id`, and a display `name`.

### My Summary Response

```json
{
  "overdue": 3,
  "due_today": 5,
  "upcoming_7_days": 12,
  "in_progress": 8,
  "total_pending": 20
}
```

---

## TaskService

**File:** `app/Services/TaskService.php`

| Method | Signature | Description |
|---|---|---|
| `list` | `list(array $filters, User $user): LengthAwarePaginator` | Apply filters, scope by user role |
| `create` | `create(array $data, User $user): Task` | Set `created_by`, resolve polymorphic entity |
| `update` | `update(Task $task, array $data): Task` | Update and return fresh |
| `delete` | `delete(Task $task): void` | Delete |
| `complete` | `complete(Task $task): Task` | Set status=completed, completed_at=now |
| `mySummary` | `mySummary(User $user): array` | Counts for overdue, today, upcoming, in_progress, pending |

### Business Rules

- `created_by` set server-side from authenticated user.
- `user_id` defaults to authenticated user if not provided (self-assign).
- Admins/managers can assign tasks to any user; sales reps can only self-assign.
- `entity_type` is validated: the referenced entity must exist.
- Completing a task sends no notification (the completion itself is the action).
- Polymorphic `taskable` resolves to `Lead`, `Customer`, or `Organization` model.

---

## TaskPolicy

| Method | Rule |
|---|---|
| `viewAny` | Authenticated (all users see their own; managers/admins see all) |
| `view` | Is assigned user OR is creator OR has `manage-tasks` |
| `create` | Authenticated |
| `update` | Is assigned user OR has `manage-tasks` |
| `delete` | Is creator OR has `manage-tasks` |
| `complete` | Is assigned user OR has `manage-tasks` |
