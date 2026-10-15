# 12 — Notifications & Reminders

Uses Laravel's built-in **database notification** system (`Illuminate\Notifications\DatabaseNotification`).

---

## Database Table

Uses Laravel's default `notifications` table (from `php artisan notifications:table`).

| Column | Type | Notes |
|---|---|---|
| `id` | `uuid` | PK |
| `type` | `string` | Notification class FQCN |
| `notifiable_type` | `string` | Always `App\Models\User` |
| `notifiable_id` | `unsignedBigInteger` | User ID |
| `data` | `json` | Payload (title, body, action_url, entity_type, entity_id) |
| `read_at` | `timestamp` | Null until read |
| `created_at` | `timestamp` | auto |
| `updated_at` | `timestamp` | auto |

---

## Notification Classes

All implement `ShouldQueue` and use the `database` channel. Each stores a structured `data` payload.

### 1. LeadAssignedNotification

**File:** `app/Notifications/LeadAssignedNotification.php`

**Triggered when:** A lead is assigned to a user via `LeadService::assign()`.

```json
{
  "title": "Lead Assigned",
  "body": "You have been assigned lead \"Acme Corp Deal\"",
  "action_url": "/leads/5",
  "entity_type": "lead",
  "entity_id": 5
}
```

### 2. LeadStatusChangedNotification

**File:** `app/Notifications/LeadStatusChangedNotification.php`

**Triggered when:** `LeadStatusChanged` event fires. Sent to the assigned user (if different from the user who changed it).

```json
{
  "title": "Lead Status Updated",
  "body": "Lead \"Acme Corp Deal\" moved from qualified to proposal",
  "action_url": "/leads/5",
  "entity_type": "lead",
  "entity_id": 5,
  "old_status": "qualified",
  "new_status": "proposal"
}
```

### 3. FollowUpReminderNotification

**File:** `app/Notifications/FollowUpReminderNotification.php`

**Triggered by:** Scheduled command (see below). Sent to the customer's account manager.

```json
{
  "title": "Follow-Up Due",
  "body": "Customer \"Acme Corp\" follow-up is due today",
  "action_url": "/customers/12",
  "entity_type": "customer",
  "entity_id": 12,
  "next_follow_up_at": "2026-03-08T09:00:00Z"
}
```

### 4. OpportunityExpiringNotification

**File:** `app/Notifications/OpportunityExpiringNotification.php`

**Triggered by:** Scheduled command. Sent to the customer's account manager when a won Opportunity's `end_date` is within 30 days.

```json
{
  "title": "Opportunity Expiring Soon",
  "body": "Opportunity \"Enterprise License\" for Acme Corp expires on 2026-04-01",
  "action_url": "/customers/12/opportunities/3",
  "entity_type": "opportunity",
  "entity_id": 3,
  "days_until_expiry": 24
}
```

### 5. CustomerHealthAlertNotification

**File:** `app/Notifications/CustomerHealthAlertNotification.php`

**Triggered by:** `CustomerActivityService::complete()` when health_score drops below 40. Sent to the account manager and all users with `manage-customers` permission.

```json
{
  "title": "Customer Health Alert",
  "body": "Customer \"Acme Corp\" health score dropped to 35",
  "action_url": "/customers/12",
  "entity_type": "customer",
  "entity_id": 12,
  "health_score": 35
}
```

### 6. TaskDueNotification

**File:** `app/Notifications/TaskDueNotification.php`

**Triggered by:** Scheduled command. Sent to the task's assigned user.

```json
{
  "title": "Task Due",
  "body": "Task \"Prepare proposal for Acme\" is due today",
  "action_url": "/tasks/8",
  "entity_type": "task",
  "entity_id": 8
}
```

### 7. ActivityReminderNotification

**File:** `app/Notifications/ActivityReminderNotification.php`

**Triggered by:** `crm:send-activity-reminders` (every 5 minutes). Sent to the activity owner, falling back to the customer's account manager when the owner is missing or inactive. Uses `database` always and `mail` when the user's `activity_reminder` preference is enabled.

```json
{
  "title": "Activity Reminder",
  "body": "Reminder: \"Renewal call\" for Acme Corp is due 2026-03-10 10:00.",
  "action_url": "/customers/12",
  "entity_type": "customer_activity",
  "entity_id": 44,
  "customer_id": 12,
  "due_date": "2026-03-10T10:00:00Z",
  "reminder_at": "2026-03-10T09:00:00Z"
}
```

---

## API Endpoints

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/notifications` | `NotificationController@index` | List authenticated user's notifications (paginated) |
| `GET` | `/notifications/unread-count` | `NotificationController@unreadCount` | Count of unread notifications |
| `PATCH` | `/notifications/{id}/read` | `NotificationController@markRead` | Mark single notification as read |
| `PATCH` | `/notifications/read-all` | `NotificationController@markAllRead` | Mark all as read |
| `DELETE` | `/notifications/{id}` | `NotificationController@destroy` | Delete a notification |

### Query Parameters (index)

| Param | Type | Description |
|---|---|---|
| `unread_only` | `bool` | If true, only unread notifications |
| `entity_type` | `string` | Filter by entity type (`lead`, `customer`, `opportunity`, `task`) |
| `per_page` | `int` | Items per page (default: 20, max: 100) |

---

## NotificationResource

**File:** `app/Http/Resources/Notification/NotificationResource.php`

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "type": "lead_assigned",
  "data": {
    "title": "Lead Assigned",
    "body": "You have been assigned lead \"Acme Corp Opportunity\"",
    "action_url": "/leads/5",
    "entity_type": "lead",
    "entity_id": 5
  },
  "read_at": null,
  "created_at": "2026-03-08T09:00:00Z"
}
```

The `type` field is simplified from the FQCN (e.g., `App\Notifications\LeadAssignedNotification` → `lead_assigned`).

---

## Scheduled Commands

### `crm:send-follow-up-reminders`

**File:** `app/Console/Commands/SendFollowUpReminders.php`

**Schedule:** Daily at 8:00 AM (`daily()->at('08:00')`)

**Behavior:**
1. Query customers where `next_follow_up_at` is today or overdue AND no reminder was sent for this follow-up date.
2. Send `FollowUpReminderNotification` to each customer's `account_manager`.

### `crm:send-opportunity-expiry-alerts`

**File:** `app/Console/Commands/SendOpportunityExpiryAlerts.php`

**Schedule:** Daily at 8:00 AM (`daily()->at('08:00')`)

**Behavior:**
1. Query won Opportunities with `end_date` within the next 30 days.
2. Send `OpportunityExpiringNotification` to the customer's account manager.
3. Only send once per Opportunity and expiry date.

### `crm:send-task-reminders`

**File:** `app/Console/Commands/SendTaskReminders.php`

**Schedule:** Daily at 8:00 AM (`daily()->at('08:00')`)

**Behavior:**
1. Query tasks where `due_date` is today and status is `pending` or `in_progress`.
2. Send `TaskDueNotification` to the task's assigned user.

### `crm:send-activity-reminders`

**File:** `app/Console/Commands/SendActivityReminders.php`

**Schedule:** Every 5 minutes (`everyFiveMinutes()->withoutOverlapping()`)

**Behavior:**
1. Query incomplete activities where `reminder_at` is past and `reminder_sent_at` is null.
2. Atomically claim each reminder (`reminder_sent_at = now()`) to prevent double-sends.
3. Send `ActivityReminderNotification` to the activity owner, or the account manager when the owner is missing/inactive.

### Registration

**File:** `routes/console.php`

```php
Schedule::command('crm:send-follow-up-reminders')->daily()->at('08:00');
Schedule::command('crm:send-opportunity-expiry-alerts')->daily()->at('08:00');
Schedule::command('crm:send-task-reminders')->daily()->at('08:00');
Schedule::command('crm:send-activity-reminders')->everyFiveMinutes()->withoutOverlapping();
```

---

## Events That Trigger Notifications

| Event / Action | Notification | Recipient |
|---|---|---|
| `LeadService::assign()` | `LeadAssignedNotification` | Assigned user |
| `LeadStatusChanged` event | `LeadStatusChangedNotification` | Assigned user (if not the changer) |
| Scheduled: follow-up due | `FollowUpReminderNotification` | Account manager |
| Scheduled: activity reminder due | `ActivityReminderNotification` | Activity owner (fallback: account manager) |
| Scheduled: won Opportunity expiring | `OpportunityExpiringNotification` | Account manager |
| Health score < 40 | `CustomerHealthAlertNotification` | Account manager + managers |
| Scheduled: task due | `TaskDueNotification` | Assigned user |

---

## User Model Addition

Add `Notifiable` trait (already present) — Laravel's built-in `$user->notifications` and `$user->unreadNotifications` relationships are used directly.
