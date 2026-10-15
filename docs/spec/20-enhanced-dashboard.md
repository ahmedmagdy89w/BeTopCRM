# 20 — Enhanced Dashboard

Replaces the basic `lead-stats` endpoint with a comprehensive dashboard covering the full CRM pipeline.

---

## API Endpoints

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/dashboard` | `DashboardController@index` | Full dashboard data |
| `GET` | `/dashboard/activity-feed` | `DashboardController@activityFeed` | Recent activity across all entities |

### Query Parameters (index)

| Param | Type | Description |
|---|---|---|
| `date_from` | `date` | Start of date range (default: 30 days ago) |
| `date_to` | `date` | End of date range (default: today) |

### Query Parameters (activity-feed)

| Param | Type | Description |
|---|---|---|
| `per_page` | `int` | Items per page (default: 20, max: 50) |

---

## Dashboard Response

```json
{
  "data": {
    "pipeline": {
      "total_leads": 142,
      "by_status": {
        "new": 30,
        "contacted": 25,
        "qualified": 20,
        "proposal": 15,
        "negotiation": 12,
        "won": 25,
        "lost": 15
      },
      "total_estimated_value": "1250000.00",
      "won_value": "450000.00",
      "conversion_rate": "17.61",
      "pipeline_value": "580000.00"
    },

    "customers": {
      "total": 45,
      "by_status": {
        "active": 32,
        "at_risk": 6,
        "churned": 4,
        "paused": 2,
        "renewed": 1
      },
      "avg_health_score": 72,
      "needing_follow_up": 8,
      "total_won_opportunity_revenue": "1800000.00"
    },

    "tasks": {
      "overdue": 3,
      "due_today": 5,
      "upcoming_7_days": 12,
      "in_progress": 8,
      "completed_this_period": 34
    },

    "revenue": {
      "this_period": "180000.00",
      "previous_period": "150000.00",
      "change_percent": "20.00",
      "by_month": [
        { "month": "2026-01", "revenue": "120000.00", "deals_closed": 4 },
        { "month": "2026-02", "revenue": "150000.00", "deals_closed": 6 },
        { "month": "2026-03", "revenue": "180000.00", "deals_closed": 5 }
      ]
    },

    "top_performers": [
      {
        "user": { "id": 2, "name": "Jane Sales" },
        "leads_won": 8,
        "won_value": "220000.00"
      },
      {
        "user": { "id": 3, "name": "Bob Rep" },
        "leads_won": 5,
        "won_value": "130000.00"
      }
    ],

    "campaigns": {
      "active": 2,
      "total_sent_this_period": 340,
      "total_recipients": 1200
    }
  }
}
```

### Sections Explained

| Section | Description |
|---|---|
| `pipeline` | Lead funnel metrics + pipeline value (sum of `estimated_value` for non-closed leads) |
| `customers` | Customer health overview, follow-up alerts |
| `tasks` | Current user's task summary (same as `tasks/my-summary` but scoped to period) |
| `revenue` | Revenue comparison, monthly trend from won opportunities |
| `top_performers` | Top 5 reps by won opportunities in the period (admin/manager only) |
| `campaigns` | Active campaigns and send volume (admin/manager only) |

### Role-Based Data

| Section | Admin | Manager | Sales Rep |
|---|---|---|---|
| `pipeline` | All leads | All leads | Own/assigned leads |
| `customers` | All | All | Own managed |
| `tasks` | All users | All users | Own tasks |
| `revenue` | All won opportunities | All won opportunities | Own won opportunities |
| `top_performers` | ✅ | ✅ | ❌ (omitted) |
| `campaigns` | ✅ | ✅ | ❌ (omitted) |

---

## Activity Feed Response

A unified chronological feed of recent actions across the CRM.

```json
{
  "data": [
    {
      "id": 1,
      "type": "lead_created",
      "description": "New lead \"Acme Corp\" created",
      "entity_type": "lead",
      "entity_id": 5,
      "entity_name": "Acme Corp",
      "user": { "id": 2, "name": "Jane Sales" },
      "created_at": "2026-03-08T14:30:00Z"
    },
    {
      "id": 2,
      "type": "lead_status_changed",
      "description": "Lead \"Beta Inc\" moved from qualified to proposal",
      "entity_type": "lead",
      "entity_id": 8,
      "entity_name": "Beta Inc",
      "metadata": { "old_status": "qualified", "new_status": "proposal" },
      "user": { "id": 3, "name": "Bob Rep" },
      "created_at": "2026-03-08T14:15:00Z"
    },
    {
      "id": 3,
      "type": "deal_created",
      "description": "Opportunity \"Enterprise License\" ($50,000) won for Acme Corp",
      "entity_type": "opportunity",
      "entity_id": 3,
      "entity_name": "Enterprise License",
      "user": { "id": 2, "name": "Jane Sales" },
      "created_at": "2026-03-08T13:00:00Z"
    },
    {
      "id": 4,
      "type": "task_completed",
      "description": "Task \"Prepare proposal\" completed",
      "entity_type": "task",
      "entity_id": 12,
      "entity_name": "Prepare proposal",
      "user": { "id": 2, "name": "Jane Sales" },
      "created_at": "2026-03-08T12:30:00Z"
    }
  ],
  "links": { "...pagination..." },
  "meta": { "...pagination..." }
}
```

### Activity Types

`lead_created`, `lead_status_changed`, `lead_assigned`, `customer_created`, `customer_activity_logged`, `deal_created`, `deal_completed`, `task_completed`, `organization_created`, `campaign_sent`

---

## DashboardService

**File:** `app/Services/DashboardService.php`

| Method | Signature | Description |
|---|---|---|
| `index` | `index(array $filters, User $user): array` | Aggregate all dashboard sections |
| `activityFeed` | `activityFeed(User $user, int $perPage): LengthAwarePaginator` | Recent activities from audit_logs |

### Business Rules

- Dashboard data is **role-scoped** — sales reps see only their own data.
- `pipeline_value` = sum of `estimated_value` where status NOT IN (`won`, `lost`).
- `revenue.change_percent` = `((this_period - previous_period) / previous_period) * 100`.
- Activity feed is built from `audit_logs` table, enriched with human-readable descriptions.
- `top_performers` limited to top 5 by `won_value` in the date range.
- All aggregate queries use `DB::raw()` — no N+1.

---

## Permissions

Dashboard access requires authentication. Data is automatically scoped by the user's role.
