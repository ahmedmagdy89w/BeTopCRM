# 11 — Reports

All endpoints are under `/api/v1/reports` and require `view-reports`. Date filters use `YYYY-MM-DD`; supported filters vary by report.

---

## Endpoints

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/reports/user-performance` | `ReportController@userPerformance` | Sales rep performance metrics |
| `GET` | `/reports/company` | `ReportController@company` | Organization-level analytics |
| `GET` | `/reports/customer` | `ReportController@customer` | Customer health and won-Opportunity analytics |
| `GET` | `/reports/lead-pipeline` | `ReportController@leadPipeline` | Lead status, source, owner, and scoring analytics |
| `GET` | `/reports/product-performance` | `ReportController@productPerformance` | Booked product sales and open opportunity interest |
| `GET` | `/reports/user-product-performance` | `ReportController@userProductPerformance` | Per-user × per-product sales breakdown |
| `GET` | `/reports/sales` | `ReportController@sales` | Won revenue grouped by month or year |
| `GET` | `/reports/user-performance/export` | `ReportController@exportUserPerformance` | Download as Excel or PDF |
| `GET` | `/reports/company/export` | `ReportController@exportCompany` | Download as Excel or PDF |
| `GET` | `/reports/customer/export` | `ReportController@exportCustomer` | Download as Excel or PDF |
| `GET` | `/reports/lead-pipeline/export` | `ReportController@exportLeadPipeline` | Download as Excel or PDF |
| `GET` | `/reports/product-performance/export` | `ReportController@exportProductPerformance` | Download as Excel or PDF |
| `GET` | `/reports/user-product-performance/export` | `ReportController@exportUserProductPerformance` | Download as Excel or PDF |
| `GET` | `/reports/sales/export` | `ReportController@exportSales` | Download as Excel or PDF |
| `POST` | `/reports/user-performance/async` | `ReportController@userPerformanceAsync` | Queue user-performance report |
| `POST` | `/reports/company/async` | `ReportController@companyAsync` | Queue company report |
| `POST` | `/reports/customer/async` | `ReportController@customerAsync` | Queue customer report |
| `POST` | `/reports/lead-pipeline/async` | `ReportController@leadPipelineAsync` | Queue lead-pipeline report |
| `POST` | `/reports/product-performance/async` | `ReportController@productPerformanceAsync` | Queue product-performance report |
| `POST` | `/reports/user-product-performance/async` | `ReportController@userProductPerformanceAsync` | Queue user-product-performance report |
| `POST` | `/reports/sales/async` | `ReportController@salesAsync` | Queue sales report |
| `POST` | `/reports/{report}/export/async` | `ReportController@export*Async` | Queue Excel or PDF export for supported reports |
---

## 1. User Performance Report

**`GET /api/v1/reports/user-performance`**

### Query Parameters

| Param | Type | Description |
|---|---|---|
| `user_id` | `int` | Optional — filter to a specific user. Omit for all users. |
| `date_from` | `date` | Optional — start of date range |
| `date_to` | `date` | Optional — end of date range |

### Response

```json
{
  "data": [
    {
      "user": {
        "id": 2,
        "name": "Jane Sales"
      },
      "leads_assigned": 45,
      "opportunities_assigned": 20,
      "opportunities_won": 12,
      "opportunities_lost": 3,
      "opportunities_in_progress": 5,
      "opportunity_win_rate": "60.00",
      "leads_won": 12,
      "leads_lost": 3,
      "leads_in_progress": 5,
      "conversion_rate": "60.00",
      "total_won_value": "320000.00",
      "avg_opportunity_value": "26666.67",
      "total_won_value_by_currency": [
        { "currency": "USD", "value": "320000.00", "won_count": 12, "avg_opportunity_value": "26666.67" }
      ],
      "activities_logged": 187,
      "activities_by_type": {
        "call": 52,
        "email": 78,
        "meeting": 30,
        "task": 15,
        "note": 12
      },
      "avg_days_to_close": 28
    }
  ],
  "summary": {
    "total_users": 5,
    "total_leads_assigned": 142,
    "total_opportunities_assigned": 76,
    "total_won_value": "1250000.00",
    "total_won_value_by_currency": [
      { "currency": "USD", "value": "1250000.00" }
    ],
    "overall_conversion_rate": "17.61"
  }
}
```

### Metrics Defined

| Metric | Calculation |
|---|---|
| `leads_assigned` | Count of unified `customers` rows where `is_lead = true` and `assigned_to = user_id` |
| `opportunities_assigned` | Count of opportunities assigned to the user |
| `opportunities_won`, `opportunities_lost`, `opportunities_in_progress` | Opportunity counts by stage |
| `opportunity_win_rate` | `(opportunities_won / opportunities_assigned) * 100` |
| `leads_won`, `leads_lost`, `leads_in_progress` | Compatibility aliases for the corresponding opportunity counts |
| `conversion_rate` | Compatibility alias for `opportunity_win_rate` |
| `total_won_value_by_currency` | Won opportunity amount and average Opportunity value grouped by ISO currency |
| `total_won_value`, `avg_opportunity_value` | Scalar fields; `null` when multiple currencies are present |
| `activities_logged` | Count of `customer_activities` by `user_id` |
| `activities_by_type` | Group count by `type` |
| `avg_days_to_close` | Average days between opportunity creation and `closed_at`, falling back to `updated_at` |

---

## 2. Company (Organization) Report

**`GET /api/v1/reports/company`**

### Query Parameters

| Param | Type | Description |
|---|---|---|
| `organization_id` | `int` | Optional — filter to a specific organization |
| `customer_id` | `int` | Optional — additional customer ID filter |
| `industry` | `string` | Optional — filter by industry |
| `date_from` | `date` | Optional — start of date range |
| `date_to` | `date` | Optional — end of date range |

### Response

```json
{
  "data": [
    {
      "organization": {
        "id": 1,
        "name": "Acme Corp",
        "industry": "Technology"
      },
      "total_leads": 25,
      "leads_by_status": {
        "new": 3,
        "contacted": 5,
        "qualified": 4,
        "proposal": 3,
        "negotiation": 2,
        "won": 6,
        "lost": 2
      },
      "conversion_rate": "24.00",
      "total_estimated_value": null,
      "total_estimated_value_by_currency": [
        { "currency": "EUR", "value": "120000.00" },
        { "currency": "USD", "value": "630000.00" }
      ],
      "total_won_value": null,
      "total_won_value_by_currency": [
        { "currency": "EUR", "value": "20000.00" },
        { "currency": "USD", "value": "300000.00" }
      ],
      "contacts_count": 8,
      "latest_activity_date": "2026-03-07T14:30:00Z"
    }
  ],
  "summary": {
    "total_organizations": 35,
    "total_leads_across_orgs": 420,
    "total_won_value": null,
    "total_won_value_by_currency": [
      { "currency": "EUR", "value": "20000.00" },
      { "currency": "USD", "value": "2480000.00" }
    ],
    "top_industries": [
      { "industry": "Technology", "won_value": null, "won_value_by_currency": [{ "currency": "EUR", "value": "20000.00" }, { "currency": "USD", "value": "780000.00" }], "lead_count": 85 },
      { "industry": "Healthcare", "won_value": "500000.00", "won_value_by_currency": [{ "currency": "USD", "value": "500000.00" }], "lead_count": 60 },
      { "industry": "Finance", "won_value": "450000.00", "won_value_by_currency": [{ "currency": "USD", "value": "450000.00" }], "lead_count": 55 }
    ]
  }
}
```

### Metrics Defined

| Metric | Calculation |
|---|---|
| `total_leads`, `leads_by_status` | Compatibility-named counts of opportunities, grouped by stage |
| `conversion_rate` | `(won opportunities / all opportunities) * 100` |
| `total_estimated_value_by_currency` | Sum of opportunity amounts grouped by currency |
| `total_won_value_by_currency` | Sum of won opportunity amounts grouped by currency |
| `total_estimated_value`, `total_won_value` | Legacy scalar fields; `null` when multiple currencies are present |
| `contacts_count` | JSON contact count stored on the customer record |
| `latest_activity_date` | Most recent `customer_activities.created_at` for the organization |
| `top_industries` | Three industries ranked by opportunity count; values remain grouped by currency |

Company date filters apply to opportunity creation dates. With zero or one currency, the legacy scalar value is returned (empty totals are `0.00`); with multiple currencies it is `null`.

---

## 3. Customer Report

**`GET /api/v1/reports/customer`**

Pulls from the unified `customers`, `customer_activities`, and won `opportunities` for a post-sale picture. Customers are rows where `is_lead = false`.

### Query Parameters

| Param | Type | Description |
|---|---|---|
| `organization_id` | `int` | Optional — filter by organization |
| `customer_id` | `int` | Optional — filter to a single customer |
| `account_manager_id` | `int` | Optional — filter by account manager |
| `status` | `string` | Optional — filter by customer status |
| `type` | `string` | Optional — `individual` or `company`; other values are ignored |
| `health_below` | `int` | Optional — only customers with health score below threshold |
| `date_from` | `date` | Optional — start of date range (based on `converted_at`) |
| `date_to` | `date` | Optional — end of date range |

### Response

```json
{
  "data": [
    {
      "customer": {
        "id": 1,
        "company_name": "Acme Corp",
        "contact_name": "Jane Smith",
        "status": "active",
        "health_score": 85
      },
      "account_manager": {
        "id": 2,
        "name": "Sales Rep"
      },
      "total_won_opportunity_value": null,
      "total_won_opportunity_value_by_currency": [
        { "currency": "EUR", "value": "25000.00" },
        { "currency": "USD", "value": "50000.00" }
      ],
      "won_opportunities_count": 2,
      "activities_count": 14,
      "activities_by_type": {
        "check_in": 6,
        "support": 4,
        "upsell": 2,
        "review": 2
      },
      "last_contacted_at": "2026-03-06T11:00:00Z",
      "days_since_last_contact": 3,
      "next_follow_up_at": "2026-03-15T09:00:00Z",
      "days_as_customer": 45,
      "converted_at": "2026-02-01"
    }
  ],
  "summary": {
    "total_customers": 45,
    "by_status": {
      "active": 32,
      "at_risk": 6,
      "churned": 4,
      "paused": 2,
      "renewed": 1
    },
    "total_won_opportunity_revenue": null,
    "total_won_opportunity_revenue_by_currency": [
      { "currency": "EUR", "value": "25000.00" },
      { "currency": "USD", "value": "1775000.00" }
    ],
    "avg_health_score": 72,
    "customers_needing_follow_up": 8,
    "avg_activities_per_customer": 11,
    "churn_rate": "8.89",
    "retention_rate": "91.11"
  }
}
```

### Metrics Defined

| Metric | Calculation |
|---|---|
| `total_customers` | Count of filtered non-lead records in `customers` |
| `total_won_opportunity_revenue_by_currency` | Sum of won Opportunity amounts grouped by currency |
| `total_won_opportunity_revenue` | Scalar field; `null` when multiple currencies are present |
| `avg_health_score` | Average of `customers.health_score` |
| `customers_needing_follow_up` | Count where `next_follow_up_at <= now()` |
| `avg_activities_per_customer` | Total `customer_activities` count / total customers |
| `churn_rate` | `(churned / total_customers) * 100` |
| `retention_rate` | `100 - churn_rate` |
| `activities_by_type` | Group count of `customer_activities.type` per customer |
| `days_since_last_contact` | `now() - last_contacted_at` in days |
| `days_as_customer` | `now() - converted_at` in days |
| Scalar currency totals | `null` when a report total spans multiple currencies; empty totals are `0.00` with an empty currency array |

---

## 4. Lead Pipeline Report

**`GET /api/v1/reports/lead-pipeline`**

Reports unified customer records where `is_lead = true`.

### Filters

| Param | Description |
|---|---|
| `assigned_to` | Filter by assigned user ID |
| `customer_id` | Filter to a single customer/lead record |
| `status` | Filter by `lead_status` |
| `source` | Filter by lead source |
| `min_score` | Minimum lead score |
| `date_from`, `date_to` | Filter by lead creation date |

`data` contains `by_status` counts, `by_source` and `by_owner` counts with average score, and up to ten highest-scored leads. `summary` contains `total_leads`, `open_leads`, `lost_leads`, `scored_leads`, and `avg_score`. Leads are open when their status is not `lost`.

---

## 5. Product Performance Report

**`GET /api/v1/reports/product-performance`**

Booked sales are calculated from `opportunity_products` attached to won opportunities, grouped by Opportunity currency. Open-pipeline interest is calculated from products on opportunities that are neither won nor lost.

### Filters

| Param | Description |
|---|---|
| `category_id` | Filter products by category |
| `date_from`, `date_to` | Filter Opportunity creation dates |

Each product row contains `booked_sales` entries (`currency`, `opportunities_count`, `quantity`, `booked_value`) and `open_pipeline` (`opportunities_count`, `quantity`). The summary reports product counts, total distinct open opportunities and interest quantity, plus `booked_sales_by_currency`. No amounts in different currencies are combined.

---

## 6. User Product Performance Report

**`GET /api/v1/reports/user-product-performance`**

Per-user × per-product sales breakdown: which rep sold how much of which item. One row per (user, product) pair that appears on an opportunity in range. Opportunities without an assignee are excluded (same convention as the user-performance report).

### Filters

| Param | Description |
|---|---|
| `user_id` | Filter to a specific user |
| `product_id` | Filter to a specific product |
| `date_from`, `date_to` | Filter Opportunity creation dates |

Each row contains `user` (`id`, `name`), `product` (`id`, `name`, `sku`), `opportunities_count`, `won_count`, `open_count` (neither won nor lost), total `quantity`, `booked_value_by_currency` (`currency`, `value` — won deals only), and `total_won_value` (scalar when a single currency is involved, else `null`). The summary reports distinct `users`, distinct `products`, and `booked_sales_by_currency`. Frontend route `reports.userProductPerformance` offers date/user/product pickers, sortable columns, and sync/async Excel/PDF export.

---

## 7. Sales Report (Monthly/Yearly)

**`GET /api/v1/reports/sales`**

Won revenue grouped by month (`YYYY-MM`) or year (`YYYY`), with open pipeline per period. Wins use `opportunities.updated_at` (same convention as the dashboard); open counts/values use `created_at`. Periods with only open pipeline still get a row (`won_count` 0).

### Filters

| Param | Description |
|---|---|
| `group_by` | `month` (default) or `year`; anything else falls back to `month` |
| `user_id` | Filter to opportunities assigned to a specific user |
| `date_from`, `date_to` | Filter by date |

Each row contains `period`, `won_count`, `revenue_by_currency` (`currency`, `value`), `total_revenue` (scalar when a single currency is involved, else `null`), `open_count`, and `open_value` (plain multi-currency sum, no currency split). The summary reports `group_by`, `periods`, `total_won_count`, and `total_revenue_by_currency`. Frontend route `reports.sales` defaults to the last 12 months and offers month/year grouping, a user picker, and sync/async Excel/PDF export. The AI snapshot carries a `sales` section (summary + latest 24 periods) under scope `all`/`reports`.

---

## Report Pagination

The unbounded lists — `customer`, `company`, and `user-product-performance` — support server-side pagination and sorting. The other reports return small/bounded datasets and are unpaginated.

| Param | Default | Description |
|---|---|---|
| `page` | `1` | 1-based page number (min 1) |
| `per_page` | `50` | Rows per page (1–100, clamped) |
| `sort_by` | report default | Whitelisted column key; unknown keys are ignored |
| `sort_dir` | `desc` | `asc` or `desc`; anything else falls back to `desc` |

Sortable keys — customer: `customer_name`, `account_manager`, `health_score`, `total_won_value`, `won_opportunities_count`, `activities_count`, `days_since_last_contact`, `days_as_customer`; company: `organization`, `industry`, `total_leads`, `conversion_rate`, `total_estimated_value`, `total_won_value`, `contacts_count`; user-product: `user`, `product`, `opportunities_count`, `won_count`, `open_count`, `quantity`, `booked_value`. Nulls always sort last in both directions; amounts spanning currencies are compared by summed numeric value.

Paged responses add `meta: { current_page, per_page, total }` while `summary` is always computed over the **full filtered scope**, never just the page. Pagination activates only when `page`/`per_page` is present — exports, async jobs, and the AI snapshot omit them and keep receiving the full dataset in natural order.

---

## Service

**File:** `app/Services/ReportService.php`

All six methods use aggregate queries and return a plain array with `data` and `summary` keys. Financial values are grouped by ISO currency; the legacy scalar amount field is `null` when multiple currencies would otherwise be combined. No exchange-rate conversion is performed.

---

## Authorization

All report endpoints are wrapped with `permission:view-reports` middleware, restricting access to `admin` and `manager` roles only.

---

## 6. Report Exports

All six report endpoints have a companion `/export` route that supports downloading as **Excel (xlsx)** or **PDF**.

### Query Parameter

| Param | Type | Default | Description |
|---|---|---|---|
| `format` | `string` | `xlsx` | `xlsx` or `pdf` |

All other filter params for the report are forwarded to the service (e.g. `user_id`, `date_from`, `date_to`, etc.).

### Excel

- Uses `maatwebsite/excel`.
- Export classes live in `app/Exports/`: `UserPerformanceExport`, `CompanyReportExport`, `CustomerReportExport`, `LeadPipelineReportExport`, and `ProductPerformanceReportExport`.
- Each implements `FromArray`, `WithHeadings`, `WithTitle`, `ShouldAutoSize`.
- Column headings use `__('reports.<key>')` so they respect the authenticated user's locale.
- A summary block is appended below the data rows.

### PDF

- Uses `barryvdh/laravel-dompdf`.
- Blade views live in `resources/views/exports/reports/`: `user-performance.blade.php`, `company.blade.php`, `customer.blade.php`, `lead-pipeline.blade.php`, and `product-performance.blade.php`.
- Each view receives `$report`, `$locale`, and `$isRtl` (boolean).
- Arabic locale triggers `dir="rtl"` and right-aligned table headers for proper RTL rendering.
- Column labels use `__('reports.<key>')` in Blade.

### Multi-language

- Translation keys live in `lang/en/reports.php` and `lang/ar/reports.php`.
- The authenticated user's stored `locale` is applied automatically by the `SetLocale` middleware before the export method runs. No extra locale handling is needed in the controller.

---

## 7. Async Report Generation (Polling)

For long-running reports on shared hosting (no WebSocket support), each report has a companion `/async` POST endpoint that queues the report via the generic `AsyncJobService` and returns a job reference immediately.

### Flow

1. **Submit:** `POST /reports/user-performance/async` (send the same filters as the sync GET)
2. **Response:** `201 Created` with `AsyncJobResource` (`status: "pending"`, `id: 42`)
3. **Poll:** `GET /async-jobs/42` — repeat until `status` is `completed` or `failed`
4. **Result:** When `completed`, the `result` field contains the full report data (same shape as the sync endpoint)

### Endpoints

| Method | URI | Action | Description |
|---|---|---|---|
| `POST` | `/reports/user-performance/async` | `ReportController@userPerformanceAsync` | Queue user-performance report |
| `POST` | `/reports/company/async` | `ReportController@companyAsync` | Queue company report |
| `POST` | `/reports/customer/async` | `ReportController@customerAsync` | Queue customer report |
| `POST` | `/reports/lead-pipeline/async` | `ReportController@leadPipelineAsync` | Queue lead-pipeline report |
| `POST` | `/reports/product-performance/async` | `ReportController@productPerformanceAsync` | Queue product-performance report |
| `POST` | `/reports/user-product-performance/async` | `ReportController@userProductPerformanceAsync` | Queue user-product-performance report |
| `POST` | `/reports/sales/async` | `ReportController@salesAsync` | Queue sales report |

### Response (201)

```json
{
  "data": {
    "id": 42,
    "type": "App\\Services\\ReportService",
    "status": "pending",
    "progress": 0,
    "started_at": null,
    "completed_at": null,
    "created_at": "2026-03-13T10:00:00Z",
    "updated_at": "2026-03-13T10:00:00Z"
  }
}
```

Poll via `GET /async-jobs/{id}` — see `AsyncJobResource` in [05-resources.md](05-resources.md) for the full shape.

### Authorization

The async report endpoints share the same `permission:view-reports` middleware as the synchronous report routes. The resulting async job is scoped to the submitting user.

---

## 8. Async Report Exports (File Generation)

For large Excel/PDF exports that may time out synchronously, each export has a companion `/export/async` POST endpoint. The file is generated in the background and stored on disk; the client polls then downloads.

### Flow

1. **Submit:** `POST /reports/user-performance/export/async?format=xlsx`
2. **Response:** `201 Created` with `AsyncJobResource` (`status: "pending"`)
3. **Poll:** `GET /async-jobs/{id}` — repeat until `completed`
4. **Download:** `GET /async-jobs/{id}/download` — streams the generated file

### Endpoints

| Method | URI | Action | Description |
|---|---|---|---|
| `POST` | `/reports/user-performance/export/async` | `ReportController@exportUserPerformanceAsync` | Queue xlsx/pdf export |
| `POST` | `/reports/company/export/async` | `ReportController@exportCompanyAsync` | Queue xlsx/pdf export |
| `POST` | `/reports/customer/export/async` | `ReportController@exportCustomerAsync` | Queue xlsx/pdf export |
| `POST` | `/reports/lead-pipeline/export/async` | `ReportController@exportLeadPipelineAsync` | Queue xlsx/pdf export |
| `POST` | `/reports/product-performance/export/async` | `ReportController@exportProductPerformanceAsync` | Queue xlsx/pdf export |
| `GET` | `/async-jobs/{asyncJob}/download` | `AsyncJobController@download` | Download completed export file |

### Query Parameter

| Param | Type | Default | Description |
|---|---|---|---|
| `format` | `string` | `xlsx` | `xlsx` or `pdf` |

All report filter params (`date_from`, `date_to`, `user_id`, etc.) are forwarded.

### Service

**File:** `app/Services/ReportExportService.php`

- Injects `ReportService` to generate report data.
- Writes Excel via `Excel::store()` or PDF via `Pdf::loadView()->output()` to `storage/app/private/exports/`.
- Returns `['file_path' => '...', 'file_name' => '...', 'mime_type' => '...']` which is stored in the async job's `result`.
- Accepts `locale` param and calls `App::setLocale()` so translated column headings work in the queue worker context.

### Download Endpoint

`GET /async-jobs/{asyncJob}/download`

- Returns `404` if job is not `completed`.
- Returns `404` if `file_path` missing from result or file doesn't exist on disk.
- Returns the file as a streamed download with the original filename and mime type.
- Authorization: same `AsyncJobPolicy@view` (own job or `manage-users`).

### Response (completed job result)

```json
{
  "file_path": "exports/user-performance-report-aBcDeFgH.xlsx",
  "file_name": "user-performance-report-aBcDeFgH.xlsx",
  "mime_type": "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
}
```
