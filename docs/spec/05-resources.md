# 05 — API Resources

---

## OrganizationResource

**File:** `app/Http/Resources/Organization/OrganizationResource.php`

```json
{
  "id": 1,
  "name": "Acme Corp",
  "industry": "Technology",
  "website": "https://acme.com",
  "email": "info@acme.com",
  "phone": "+1-555-0100",
  "address": {
    "line_1": "123 Main St",
    "line_2": "Suite 400",
    "city": "San Francisco",
    "state": "CA",
    "postal_code": "94102",
    "country": "US"
  },
  "notes": "Key enterprise client",
  "leads_count": 5,
  "contacts_count": 3,
  "created_by": {
    "id": 1,
    "name": "John Doe"
  },
  "created_at": "2026-03-01T10:00:00Z",
  "updated_at": "2026-03-05T14:30:00Z"
}
```

- `leads_count` and `contacts_count` included via `withCount` when loaded.
- `created_by` is a condensed user object.
- Address fields grouped into nested `address` object.

---

## LeadResource

**File:** `app/Http/Resources/Lead/LeadResource.php`

```json
{
  "id": 1,
  "first_name": "Jane",
  "last_name": "Smith",
  "full_name": "Jane Smith",
  "email": "jane@example.com",
  "phone": "+1-555-0200",
  "title": "VP of Engineering",
  "source": "referral",
  "status": "qualified",
  "estimated_value": "50000.00",
  "expected_close_date": "2026-06-15",
  "description": "Interested in enterprise plan",
  "organization": {
    "id": 1,
    "name": "Acme Corp"
  },
  "assigned_to": {
    "id": 2,
    "name": "Sales Rep"
  },
  "created_by": {
    "id": 1,
    "name": "John Doe"
  },
  "activities_count": 8,
  "created_at": "2026-03-01T10:00:00Z",
  "updated_at": "2026-03-05T14:30:00Z"
}
```

- `organization`, `assigned_to`, `created_by` included via `whenLoaded`.
- `full_name` is a computed field.
- `activities_count` included via `withCount` when loaded.

---

## LeadActivityResource

**File:** `app/Http/Resources/LeadActivity/LeadActivityResource.php`

```json
{
  "id": 1,
  "type": "call",
  "subject": "Discovery call",
  "description": "Discussed requirements and timeline",
  "due_date": "2026-03-10T15:00:00Z",
  "completed_at": null,
  "is_completed": false,
  "user": {
    "id": 2,
    "name": "Sales Rep"
  },
  "created_at": "2026-03-08T09:00:00Z",
  "updated_at": "2026-03-08T09:00:00Z"
}
```

---

## ContactResource

**File:** `app/Http/Resources/Contact/ContactResource.php`

```json
{
  "id": 1,
  "first_name": "Bob",
  "last_name": "Jones",
  "full_name": "Bob Jones",
  "email": "bob@acme.com",
  "phone": "+1-555-0300",
  "title": "CTO",
  "is_primary": true,
  "created_at": "2026-03-01T10:00:00Z",
  "updated_at": "2026-03-01T10:00:00Z"
}
```

---

## Collection Resources

`OrganizationCollection`, `LeadCollection`, and `CustomerCollection` extend `ResourceCollection` and include standard Laravel pagination metadata (`links`, `meta`).

---

## CustomerResource

**File:** `app/Http/Resources/Customer/CustomerResource.php`

```json
{
  "id": 1,
  "company_name": "Acme Corp",
  "contact_name": "Jane Smith",
  "contact_email": "jane@example.com",
  "contact_phone": "+1-555-0200",
  "status": "active",
  "contract_value": "50000.00",
  "notes": "Enterprise plan, annual billing",
  "converted_at": "2026-02-28T16:00:00Z",
  "lead": {
    "id": 10,
    "full_name": "Jane Smith",
    "source": "referral"
  },
  "organization": {
    "id": 1,
    "name": "Acme Corp"
  },
  "account_manager": {
    "id": 3,
    "name": "Account Manager"
  },
  "health_score": 85,
  "last_contacted_at": "2026-03-06T11:00:00Z",
  "next_follow_up_at": "2026-03-15T09:00:00Z",
  "activities_count": 12,
  "created_at": "2026-02-28T16:00:00Z",
  "updated_at": "2026-03-05T10:00:00Z"
}
```

- `lead`, `organization`, `account_manager` included via `whenLoaded`.
- `activities_count` is included when available. `contract_value` is derived from won Opportunities; contract dates are returned by the Opportunity resource.

---

## CustomerActivityResource

**File:** `app/Http/Resources/CustomerActivity/CustomerActivityResource.php`

```json
{
  "id": 1,
  "type": "follow_up",
  "channel": "call",
  "subject": "Monthly check-in call",
  "description": "Discussed satisfaction and upcoming renewal",
  "outcome": "positive",
  "due_date": "2026-03-10T10:00:00Z",
  "reminder_at": "2026-03-10T09:00:00Z",
  "reminder_sent_at": "2026-03-10T09:00:05Z",
  "completed_at": "2026-03-10T10:30:00Z",
  "is_completed": true,
  "is_overdue": false,
  "user": {
    "id": 3,
    "name": "Account Manager"
  },
  "customer": {
    "id": 12,
    "display_name": "Acme Corp"
  },
  "created_at": "2026-03-08T09:00:00Z",
  "updated_at": "2026-03-10T10:30:00Z"
}
```

The `customer` block is included on the global list; `is_overdue` is computed server-side.

---

## Retired DealResource

Deal responses have been removed. Current contract details are exposed through OpportunityResource.

**File:** `app/Http/Resources/Deal/DealResource.php`

```json
{
  "id": 1,
  "title": "Enterprise Annual License",
  "type": "initial",
  "value": "50000.00",
  "currency": "USD",
  "status": "active",
  "start_date": "2026-03-01",
  "end_date": "2027-03-01",
  "notes": "Includes premium support",
  "created_by": {
    "id": 2,
    "name": "Sales Rep"
  },
  "created_at": "2026-03-01T10:00:00Z",
  "updated_at": "2026-03-01T10:00:00Z"
}
```

---

## QuotationListResource

**File:** `app/Http/Resources/Quotation/QuotationListResource.php`

```json
{
  "document_type": "quotation",
  "id": 12,
  "quotation_number": "QT-2026-0012",
  "status": "sent",
  "currency": "USD",
  "total": "1250.00",
  "valid_until": "2026-10-25",
  "sent_at": "2026-09-25T10:30:00Z",
  "customer_id": 4,
  "opportunity_id": 8,
  "customer": {
    "id": 4,
    "display_name": "Acme Corp"
  },
  "opportunity": {
    "id": 8,
    "title": "Enterprise annual license"
  },
  "created_at": "2026-09-25T10:00:00Z"
}
```

- Intended for the global paginated `GET /quotations` list; it omits quotation items and document-rendering data.
- `customer` and `opportunity` are condensed summaries loaded by `QuotationService::list()`.

---

## InvoiceListResource

**File:** `app/Http/Resources/Invoice/InvoiceListResource.php`

```json
{
  "document_type": "invoice",
  "id": 5,
  "invoice_number": "INV-2026-0005",
  "status": "issued",
  "currency": "USD",
  "total": "1250.00",
  "issued_at": "2026-09-25",
  "customer_id": 4,
  "opportunity_id": 8,
  "quotation_id": 12,
  "customer": {
    "id": 4,
    "display_name": "Acme Corp"
  },
  "opportunity": {
    "id": 8,
    "title": "Enterprise annual license"
  },
  "created_at": "2026-09-25T11:00:00Z"
}
```

- Intended for the global paginated `GET /invoices` list; it omits invoice items and document-rendering data.
- `customer` and `opportunity` are condensed summaries loaded by `InvoiceService::list()`.

---

## AsyncJobResource

**File:** `app/Http/Resources/AsyncJob/AsyncJobResource.php`

```json
{
  "id": 1,
  "type": "App\\Services\\ReportService",
  "status": "completed",
  "progress": 100,
  "input": { "method": "userPerformance", "params": [{}] },
  "result": { "data": [...], "summary": {...} },
  "error": null,
  "started_at": "2026-03-13T10:00:01Z",
  "completed_at": "2026-03-13T10:00:05Z",
  "created_at": "2026-03-13T10:00:00Z",
  "updated_at": "2026-03-13T10:00:05Z"
}
```

> **Conditional fields:** `input` and `result` are only included when the job is finished (`completed` or `failed`). `error` is only included when the job has `failed`.

---

## AiChatSuggestedQuestionsResource

**File:** `app/Http/Resources/AiChat/AiChatSuggestedQuestionsResource.php`

Wraps the localized starter-question list for `GET /ai-chat/suggested-questions`:

```json
{
    "data": {
        "questions": ["What is our lead conversion rate this month?", "..."]
    }
}
```

---

## TaxResource

**File:** `app/Http/Resources/Tax/TaxResource.php`

```json
{
    "id": 2,
    "name": "VAT 14",
    "percent": "14.00",
    "is_active": true,
    "products_count": 5,
    "created_at": "2026-10-04T10:00:00Z"
}
```

`products_count` is included via `loadCount` on `show` only.

---

## Document line items (discount + tax snapshots)

`QuotationItemResource` and `InvoiceItemResource` snapshot the line discount and tax so documents survive later product/tax edits:

```json
{
    "id": 1,
    "product_id": 3,
    "product_name": "CRM Pro License",
    "sku": "CRM-PRO-001",
    "quantity": 2,
    "unit_price": "90.00",
    "subtotal": "180.00",
    "discount_percent": "10.00",
    "discount_amount": "18.00",
    "tax_id": 2,
    "tax_name": "VAT 14",
    "tax_percent": "14.00",
    "tax_amount": "22.68",
    "notes": null
}
```

`discount_percent` is `null` for amount-only lines. Header `tax_percent` on quotations/invoices is legacy and stored as `0`.

---

## ProductResource additions

`ProductResource` exposes `tax_id` plus a condensed `tax` object (`id`, `name`, `percent`) when the relation is loaded, and pivot `discount_percent` / `discount_amount` on opportunity lines (insertion order).
