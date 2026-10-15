# 04 — Validation Rules (Form Requests)

> Change #1: `Organization`, `Contact`, `Lead` requests removed. `Customer` requests now validate `type` (individual|company), `is_lead`, `lead_status`, nested `contacts[]`/`addresses[]`. New `Opportunity` requests validate `stage` transitions.

---

## Password Reset OTP

### RequestPasswordResetOtpRequest

**File:** `app/Http/Requests/Auth/RequestPasswordResetOtpRequest.php`

```php
'email' => ['required', 'email']
```

### ResetPasswordOtpRequest

**File:** `app/Http/Requests/Auth/ResetPasswordOtpRequest.php`

```php
'email' => ['required', 'email'],
'otp' => ['required', 'digits:6'],
'password' => ['required', 'string', 'min:8', 'confirmed'],
```

OTP verification additionally enforces expiry, maximum failed attempts, and single-use consumption.

---

## Customer

### StoreCustomerRequest

**File:** `app/Http/Requests/Customer/StoreCustomerRequest.php`

```php
public function rules(): array
{
    return [
        'type' => ['required', Rule::in(Customer::TYPES)],
        'company_name' => ['required_if:type,company', 'nullable', 'string', 'max:255'],
        'first_name' => ['required_if:type,individual', 'nullable', 'string', 'max:100'],
        'last_name' => ['required_if:type,individual', 'nullable', 'string', 'max:100'],
        'is_lead' => ['sometimes', 'boolean'],
        'lead_status' => ['nullable', Rule::in(Customer::LEAD_STATUSES)],
        'status' => ['sometimes', Rule::in(Customer::CUSTOMER_STATUSES)],
        'contacts' => ['nullable', 'array'],
        'contacts.*.first_name' => ['required_with:contacts', 'string', 'max:100'],
        'contacts.*.email' => ['nullable', 'email'],
        'addresses' => ['nullable', 'array'],
        'addresses.*.city' => ['nullable', 'string', 'max:100'],
        // + assigned_to, source, estimated_value, contract_*, health_score, tag_ids, custom_fields
    ];
}
```

### UpdateCustomerRequest

Same as Store but all fields `sometimes`. Both requests accept the legacy `contact_name` / `contact_email` / `contact_phone` snapshot fields.

> `CustomerService::create()` / `update()` auto-derive the snapshot from the primary (or first) `contacts[]` entry whenever `contacts` are submitted and no explicit snapshot value was sent. Partial updates that omit `contacts` leave the snapshot untouched.

### UpdateCustomerLeadStatusRequest

Validates `lead_status` + `canTransitionLeadTo()` (only when `is_lead`).

### AssignCustomerRequest

`assigned_to` required `exists:users,id`.

---

## Opportunity

### StoreOpportunityRequest / UpdateOpportunityRequest / UpdateOpportunityStageRequest

`title` required, `stage` in `Opportunity::STAGES`, stage transitions validated via `canTransitionTo()`.

---

## Taxes

### StoreTaxRequest

**File:** `app/Http/Requests/Tax/StoreTaxRequest.php`

```php
'name'    => ['required', 'string', 'max:100', 'unique:taxes,name'],
'percent' => ['required', 'numeric', 'min:0', 'max:100'],
'is_active' => ['sometimes', 'boolean'],
```

### UpdateTaxRequest

Same as Store but all fields `sometimes`; `name` unique ignores the current tax.

---

## Opportunity Products (line discounts)

### StoreOpportunityProductRequest / UpdateOpportunityProductRequest

**Files:** `app/Http/Requests/OpportunityProduct/*.php`

```php
'product_id'       => ['required', 'integer', 'exists:products,id'],
'quantity'         => ['sometimes', 'integer', 'min:1'],
'unit_price'       => ['nullable', 'numeric', 'min:0'],
'discount_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
'discount_amount'  => ['sometimes', 'numeric', 'min:0'],
'notes'            => ['nullable', 'string'],
```

Update rules are all optional. Percent wins when present (amount derived server-side); otherwise the amount is capped at line gross. The sync endpoint validates the same per-item fields (`products.*.discount_percent`, `products.*.discount_amount`, plus `unit_price`).

---

## Quotations

### StoreQuotationRequest

**File:** `app/Http/Requests/Quotation/StoreQuotationRequest.php`

```php
'valid_until' => ['sometimes', 'date', 'after_or_equal:today'],
'notes'       => ['nullable', 'string', 'max:5000'],
'terms'       => ['nullable', 'string', 'max:5000'],
```

There is intentionally **no** `tax_percent` / `discount_amount` input — all totals derive from opportunity lines via `DocumentTotals` (see [24-products-catalog.md](24-products-catalog.md)).

`terms` is optional and stays `null` unless the caller supplies custom text. A null `terms` renders the locale default (`messages.quotation_default_terms`) in the preview, PDF, and email, so default terms always follow the display language instead of freezing the creation-time wording.

---

## Products

`StoreProductRequest` / `UpdateProductRequest` accept `tax_id` (`nullable|exists:taxes,id`, `sometimes` on update). Attaching an inactive tax is rejected by `ProductService` with a `tax_id` error.

---

## Customer Activity

### StoreCustomerActivityRequest

**File:** `app/Http/Requests/CustomerActivity/StoreCustomerActivityRequest.php`

```php
public function rules(): array
{
    return [
        'type'        => ['required', 'string', Rule::in(CustomerActivity::TYPES)],
        'channel'     => ['nullable', 'string', Rule::in(CustomerActivity::CHANNELS)],
        'subject'     => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
        'due_date'    => ['nullable', 'date'],            // required when type=follow_up
        'reminder_at' => ['nullable', 'date', 'after:now'], // must be future and <= due_date when both set
    ];
}
```

An `after` validator rejects `reminder_at` past `due_date` (`messages` key `validation.before_or_equal`), and `due_date` becomes `required` when `type` is `follow_up`.

### UpdateCustomerActivityRequest

Same as Store but all fields `sometimes`, with two effective-value rules so partial updates cannot bypass validation:

- `reminder_at` / `due_date` are checked against the **merged** value (request input if the key is present, otherwise the stored activity value). Updating only `reminder_at` past the stored `due_date` — or only `due_date` before the stored `reminder_at` — fails with a `reminder_at` error.
- `due_date` is required when the effective type (request `type`, else stored type) is `follow_up` and the effective `due_date` is empty.

The Opportunity counterparts (`StoreOpportunityActivityRequest` / `UpdateOpportunityActivityRequest`) enforce the identical reminder rules; only the customer variant adds the `follow_up` due-date requirement.

### CompleteCustomerActivityRequest

**File:** `app/Http/Requests/CustomerActivity/CompleteCustomerActivityRequest.php`

```php
public function rules(): array
{
    return [
        'outcome' => ['nullable', 'string', Rule::in(CustomerActivity::OUTCOMES)],
    ];
}
```

---

## Retired Deal Requests

Deal requests have been removed. Use the Opportunity request rules for current contract data.

### StoreDealRequest

**File:** `app/Http/Requests/Deal/StoreDealRequest.php`

```php
public function rules(): array
{
    return [
        'title'      => ['required', 'string', 'max:255'],
        'type'       => ['required', 'string', Rule::in(Deal::TYPES)],
        'value'      => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        'currency'   => ['sometimes', 'string', 'size:3'],
        'status'     => ['sometimes', 'string', Rule::in(Deal::STATUSES)],
        'start_date' => ['required', 'date'],
        'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        'notes'      => ['nullable', 'string'],
    ];
}
```

### UpdateDealRequest

Same as Store but all fields `sometimes`.
