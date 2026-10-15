# 07 — Events & Listeners

---

## LeadStatusChanged

**File:** `app/Events/LeadStatusChanged.php`

### Payload

| Property | Type | Description |
|---|---|---|
| `lead` | `Lead` | The lead model instance |
| `oldStatus` | `string` | Previous status value |
| `newStatus` | `string` | New status value |
| `changedBy` | `User` | The user who initiated the change |

### Dispatched By

`LeadService::updateStatus()`

---

## LogLeadStatusChange (Listener)

**File:** `app/Listeners/LogLeadStatusChange.php`

### Behavior

- Logs the status change to Laravel's log channel: `"Lead #{id} ({name}) status changed from {old} to {new} by {user}"`
- Future: write to an `audit_log` table, send notification to assigned user, etc.

---

## Registration

**File:** `app/Providers/AppServiceProvider.php`

```php
Event::listen(LeadStatusChanged::class, LogLeadStatusChange::class);
```
