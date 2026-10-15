# 28 — Activity Timeline

Unified per-entity timeline that aggregates activities, status changes, notes, attachments, emails, and audit entries into one chronological feed. Read-only aggregation — no new tables.

---

## Approach

The timeline is a **virtual feed** assembled from existing data sources. No new database table is needed. The `TimelineService` queries multiple tables and merges results chronologically.

---

## Data Sources

For a given entity (e.g., Lead #42), the timeline pulls from:

| Source | Condition | Event Examples |
|---|---|---|
| `lead_activities` / `customer_activities` | Direct relationship | "Call logged", "Meeting completed" |
| `audit_logs` | `auditable_type/id` matches entity | "Status changed from new to contacted" |
| `notes` | `notable_type/id` matches entity | "Note added by Ahmed" |
| `attachments` | `attachable_type/id` matches entity | "Document uploaded: proposal.pdf" |
| `tasks` | `taskable_type/id` matches entity | "Task created: Send quote", "Task completed" |
| `opportunities` | Relationship to entity (e.g., customer's opportunities) | "Opportunity created: Enterprise License" |
| `campaign_recipients` | Where `lead_id` or `contact_id` matches | "Campaign email sent", "Email opened" |

---

## Service

### TimelineService

```php
class TimelineService
{
    public function forEntity(string $entityType, int $entityId, array $filters = []): LengthAwarePaginator
    {
        // 1. Resolve entity model, verify it exists
        // 2. Collect events from each source
        // 3. Merge into unified collection
        // 4. Sort by timestamp descending
        // 5. Apply filters (type, date range)
        // 6. Paginate

        return $this->buildTimeline($entity, $filters);
    }

    private function getActivities(Model $entity): Collection
    private function getAuditEntries(Model $entity): Collection
    private function getNotes(Model $entity): Collection
    private function getAttachments(Model $entity): Collection
    private function getTasks(Model $entity): Collection
    private function getEmailEvents(Model $entity): Collection
}
```

**Implementation strategy:** Use `UNION ALL` queries where possible for efficiency, or collect from each source with a `limit` and merge in PHP. Each source returns items in a normalized format.

---

## Timeline Event Format

Each timeline event is normalized to:

```json
{
    "id": "activity_15",
    "type": "activity",
    "subtype": "call",
    "title": "Phone call with client",
    "description": "Discussed pricing for Q3 renewal",
    "user": { "id": 5, "name": "Ahmed" },
    "metadata": {
        "outcome": "positive",
        "duration_minutes": 30
    },
    "timestamp": "2026-03-08T14:30:00Z"
}
```

### Event types

| Type | Subtypes | Source |
|---|---|---|
| `activity` | `call`, `email`, `meeting`, `note`, `task`, `follow_up`, `review`, `escalation`, `whatsapp`, `other` | lead_activities / customer_activities |
| `status_change` | — | audit_logs (where field = status) |
| `field_update` | — | audit_logs (other field changes) |
| `note` | `comment`, `reply` | notes |
| `attachment` | `upload` | attachments |
| `task` | `created`, `completed` | tasks |
| `opportunity` | `created`, `updated`, `stage_changed`, `deleted` | opportunities |
| `email` | `sent`, `opened`, `clicked` | campaign_recipients |
| `created` | — | audit_logs (action = created) |
| `assigned` | — | audit_logs (where field = assigned_to) |

Outgoing WhatsApp messages are recorded as completed `whatsapp` customer activities and, when sent from an opportunity, as opportunity activities. The timeline displays these through its existing activity source. Click-to-Chat records initiation only; it does not confirm that a message was sent or delivered.

---

## Filters

| Param | Type | Description |
|---|---|---|
| `type` | `string` | Filter by event type (comma-separated) |
| `from` | `date` | Events after this date |
| `to` | `date` | Events before this date |
| `user_id` | `integer` | Events by specific user |

Example: `GET /leads/42/timeline?type=activity,note&from=2026-03-01`

---

## API Resource

### TimelineEventResource

```json
{
    "data": [
        {
            "id": "audit_234",
            "type": "status_change",
            "title": "Status changed",
            "description": "new → contacted",
            "user": { "id": 5, "name": "Ahmed" },
            "metadata": { "old_value": "new", "new_value": "contacted" },
            "timestamp": "2026-03-08T15:00:00Z"
        },
        {
            "id": "note_12",
            "type": "note",
            "title": "Note added",
            "description": "Spoke with client about renewal...",
            "user": { "id": 5, "name": "Ahmed" },
            "metadata": { "is_pinned": false, "replies_count": 2 },
            "timestamp": "2026-03-08T14:45:00Z"
        },
        {
            "id": "activity_15",
            "type": "activity",
            "subtype": "call",
            "title": "Phone call",
            "description": "Discussed pricing for Q3 renewal",
            "user": { "id": 5, "name": "Ahmed" },
            "metadata": { "outcome": "positive" },
            "timestamp": "2026-03-08T14:30:00Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 25,
        "total": 87
    }
}
```

---

## Controller

### TimelineController

```php
class TimelineController extends Controller
{
    public function index(string $entityType, int $entityId, Request $request)
    {
        // Resolve entity, authorize (can user view this entity?)
        // Delegate to TimelineService
        // Return paginated TimelineEventResource collection
    }
}
```

---

## Endpoints

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/{entity}/{id}/timeline` | `TimelineController@index` | Get unified timeline for entity |

> `{entity}` = `customers`, `opportunities`

---

## Permissions

No new permissions. Timeline access follows the parent entity's view policy — if you can view the entity, you can see its timeline.

---

## Performance Considerations

- Each data source query is limited to the page size before merging
- Use database-level `UNION ALL` for audit_logs + activities where possible
- Cache timeline for frequently accessed entities (5-minute TTL)
- Index on `(auditable_type, auditable_id, created_at)` for audit_logs
- Index on `(notable_type, notable_id, created_at)` for notes
- Index on `(attachable_type, attachable_id)` for attachments
