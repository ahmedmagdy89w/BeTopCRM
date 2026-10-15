# 30 — Company Settings

Company-wide configuration including branding (name, logo), contact info, address, regional defaults, AI provider, and product terminology. Settings are stored in a key-value table, served through a dedicated set of API endpoints, and read through a forever-cache that is busted on every write.

---

## Database

### `company_settings` table

**Migration:** `create_company_settings_table`

| Column | Type | Constraints |
|---|---|---|
| `id` | `bigIncrements` | PK |
| `key` | `string(100)` | unique, not null |
| `value` | `text` | nullable |
| `created_at` | `timestamp` | |
| `updated_at` | `timestamp` | |

One row per setting key. The table starts empty; fields are written on first save and default to `null` until set.

---

## Setting Keys

| Key | Description | Example value |
|---|---|---|
| `company_name` | Legal or display name | `"ACME Corp"` |
| `company_email` | Public contact email | `"info@acme.com"` |
| `company_phone` | Public contact phone | `"+1-555-0100"` |
| `company_website` | Company website URL | `"https://acme.com"` |
| `company_tax_number` | VAT / tax registration number | `"GB123456789"` |
| `company_currency` | ISO 4217 3-char currency code | `"USD"` |
| `company_timezone` | IANA timezone identifier | `"America/New_York"` |
| `address_street` | Street address | `"100 Main St"` |
| `address_city` | City | `"New York"` |
| `address_state` | State / province | `"NY"` |
| `address_country` | Country | `"US"` |
| `address_zip` | Postal / ZIP code | `"10001"` |
| `company_logo_path` | Path to logo on `public` disk | `"company/logo/{uuid}.png"` |
| `ai_provider` | AI provider (Laravel AI driver): `gemini, openai, anthropic, ollama, groq, deepseek, mistral, openrouter, xai, azure` | `"gemini"` |
| `ai_model` | Model name for the provider | `"gemini-2.0-flash"` |
| `ai_api_key` | Provider API key, **encrypted at rest**; explicitly revealable by users with update permission | `"secret..."` |
| `ai_base_url` | Optional custom endpoint (self-hosted Ollama, Azure, proxy) | `"http://localhost:11434"` |
| `product_singular_en` | Custom singular product term (English), e.g. for service businesses | `"Service"` |
| `product_plural_en` | Custom plural product term (English) | `"Services"` |
| `product_singular_ar` | Custom singular product term (Arabic) | `"خدمة"` |
| `product_plural_ar` | Custom plural product term (Arabic) | `"خدمات"` |
| `quotations_enabled` | Quotations module on/off (stored `'1'`/`'0'`, default ON when absent) | `"1"` |
| `invoices_enabled` | Invoices module on/off (stored `'1'`/`'0'`, default ON when absent) | `"1"` |

---

## API Endpoints

All endpoints are prefixed with `/api/v1` and require `auth:sanctum`.

| Method | URI | Controller action | Auth |
|---|---|---|---|
| `GET` | `/company/settings` | `CompanySettingsController@show` | `view-company-settings` |
| `PUT` | `/company/settings` | `CompanySettingsController@update` | `update-company-settings` |
| `POST` | `/company/settings/ai-key/reveal` | `CompanySettingsController@revealAiApiKey` | `update-company-settings` |
| `POST` | `/company/settings/logo` | `CompanySettingsController@uploadLogo` | `update-company-settings` |
| `DELETE` | `/company/settings/logo` | `CompanySettingsController@deleteLogo` | `update-company-settings` |

---

## Permissions

| Permission | Admin | Manager | Sales rep |
|---|---|---|---|
| `view-company-settings` | ✅ | ✅ | ❌ |
| `update-company-settings` | ✅ | ❌ | ❌ |

---

## Validation

### UpdateCompanySettingsRequest

**File:** `app/Http/Requests/CompanySettings/UpdateCompanySettingsRequest.php`

```php
public function rules(): array
{
    return [
        'company_name'       => ['sometimes', 'string', 'max:255'],
        'company_email'      => ['sometimes', 'nullable', 'email', 'max:255'],
        'company_phone'      => ['sometimes', 'nullable', 'string', 'max:30'],
        'company_website'    => ['sometimes', 'nullable', 'url', 'max:255'],
        'company_tax_number' => ['sometimes', 'nullable', 'string', 'max:100'],
        'company_currency'   => ['sometimes', 'nullable', 'string', 'size:3'],
        'company_timezone'   => ['sometimes', 'nullable', 'string', 'timezone'],
        'address'            => ['sometimes', 'array'],
        'address.street'     => ['sometimes', 'nullable', 'string', 'max:255'],
        'address.city'       => ['sometimes', 'nullable', 'string', 'max:255'],
        'address.state'      => ['sometimes', 'nullable', 'string', 'max:255'],
        'address.country'    => ['sometimes', 'nullable', 'string', 'max:255'],
        'address.zip'        => ['sometimes', 'nullable', 'string', 'max:20'],
        'ai'                 => ['sometimes', 'array'],
        'ai.provider'        => ['sometimes', 'nullable', 'string', 'in:gemini,openai,anthropic,ollama,groq,deepseek,mistral,openrouter,xai,azure'],
        'ai.model'           => ['sometimes', 'nullable', 'string', 'max:100'],
        'ai.api_key'         => ['sometimes', 'nullable', 'string', 'max:2000'],
        'ai.base_url'        => ['sometimes', 'nullable', 'url', 'max:500'],
        'product_labels'             => ['sometimes', 'array'],
        'product_labels.singular_en' => ['sometimes', 'nullable', 'string', 'max:60'],
        'product_labels.plural_en'   => ['sometimes', 'nullable', 'string', 'max:60'],
        'product_labels.singular_ar' => ['sometimes', 'nullable', 'string', 'max:60'],
        'product_labels.plural_ar'   => ['sometimes', 'nullable', 'string', 'max:60'],
        'features'                   => ['sometimes', 'array'],
        'features.quotations_enabled' => ['sometimes', 'boolean'],
        'features.invoices_enabled'   => ['sometimes', 'boolean'],
    ];
}
```

### UploadCompanyLogoRequest

**File:** `app/Http/Requests/CompanySettings/UploadCompanyLogoRequest.php`

```php
public function rules(): array
{
    return [
        'logo' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png'],  // 2 MB
    ];
}
```

---

## Resource

### CompanySettingResource — `GET /company/settings` response shape

**File:** `app/Http/Resources/CompanySettings/CompanySettingResource.php`

```json
{
  "data": {
    "company_name": "ACME Corp",
    "company_email": "info@acme.com",
    "company_phone": "+1-555-0100",
    "company_website": "https://acme.com",
    "company_tax_number": "GB123456789",
    "company_currency": "USD",
    "company_timezone": "America/New_York",
    "address": {
      "street": "100 Main St",
      "city": "New York",
      "state": "NY",
      "country": "US",
      "zip": "10001"
    },
    "logo_url": "/storage/company/logo/550e8400-e29b-41d4-a716-446655440000.png",
    "ai": {
      "provider": "gemini",
      "model": "gemini-2.0-flash",
      "base_url": null,
      "api_key_set": true,
      "api_key_masked": "****5678"
    },
    "product_labels": {
      "singular_en": "Service",
      "plural_en": "Services",
      "singular_ar": "خدمة",
      "plural_ar": "خدمات"
    },
    "features": {
      "quotations_enabled": true,
      "invoices_enabled": true
    }
  }
}
```

The normal settings response returns only `api_key_set` and the masked hint. A separate, explicitly invoked `POST /company/settings/ai-key/reveal` action returns the decrypted key only to users with `update-company-settings`; the plaintext is not included in normal settings loads. An absent or empty `ai.api_key` keeps the stored value (v1 has no clear-key action). Stored keys are encrypted via `Crypt`.

All fields default to `null` until explicitly set. `logo_url` is a full public URL generated via `Storage::disk('public')->url(path)`.

---

## Model

**File:** `app/Models/CompanySetting.php`

```php
class CompanySetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, mixed $default = null): mixed;
    public static function setValue(string $key, mixed $value): void;          // busts the cache
    public static function setMany(array $keyValues): void;                    // single bust after all writes
    public static function getAllAsArray(): array;  // ['key' => 'value', ...] — uncached, prefer cachedAll()
    public static function cachedAll(): array;      // Cache::rememberForever('company_settings.all')
    public static function forgetCache(): void;     // call from any direct-write/delete path
}
```

No soft-deletes. `updateOrCreate(['key' => $key], ['value' => $value])` is used for writes.

### Caching

Reads go through `cachedAll()` (forever-cache, one entry for the whole table). Every write via `setValue()` / `setMany()` busts it; `deleteLogo()` busts explicitly because it deletes the row via query builder. `AiSettingsResolver` and `WhatsAppService` also read from the cache. Feature tests clear it in `beforeEach` (`CompanySetting::forgetCache()`) so no state leaks between tests.

---

## Service

**File:** `app/Services/CompanySettingsService.php`

| Method | Signature | Description |
|---|---|---|
| `get` | `get(): array` | Load all keys (cached), return structured response array |
| `update` | `update(array $data): array` | Persist validated fields, return `get()` |
| `uploadLogo` | `uploadLogo(UploadedFile $file): array` | Delete old logo, store new file, persist path key, return `get()` |
| `deleteLogo` | `deleteLogo(): void` | Delete file from disk, remove `company_logo_path` row |
| `productLabels` | `productLabels(?string $locale, string $fallbackSingular, string $fallbackPlural): array` | `{singular, plural}` for a locale from the cache; falls back when unset |
| `productReportTitle` | `productReportTitle(?string $locale): string` | Label-aware product-performance report title |
| `isQuotationsEnabled` | `isQuotationsEnabled(): bool` | Static; missing key defaults to `true` |
| `isInvoicesEnabled` | `isInvoicesEnabled(): bool` | Static; missing key defaults to `true` |

### Business Rules

- Logo stored on the `public` disk under `company/logo/{uuid}.{ext}`.
- Uploading a new logo deletes the previously stored file before storing the new one.
- `company_currency` is stored as-is (3-char string); no ISO 4217 enforcement beyond `size:3` validation.
- `company_timezone` accepts any value valid under PHP's `timezone` validation rule.
- Partial updates are supported — only the keys present in the request are written; unset keys are unchanged.
- The `address` object in the request is flattened to `address_street`, `address_city`, etc. when persisting.
- The `ai` object is mapped to `ai_provider`, `ai_model`, `ai_api_key`, `ai_base_url` rows. `ai_api_key` is encrypted on write and only replaced when a non-empty value is sent.
- `company_currency` (3-char ISO code, stored uppercase) is the app-wide currency. Opportunities, products, product imports, quotations, and invoices always use it — per-record currency is not accepted input anywhere (services force `CompanySettingsService::defaultCurrency()` on create and strip it on update). Changing the setting backfills `opportunities`, `products`, `quotations`, and `invoices` to the new code (query-builder update, no audit entries).
- **Product terminology.** The `product_labels` object (`singular_en/plural_en/singular_ar/plural_ar`, each `nullable|string|max:60`) lets an admin rename Products everywhere — e.g. a services business sets `Service/Services/خدمة/خدمات`. `null`/blank means "use the built-in i18n strings". The API exposes the raw four values; resolution happens per surface: `CompanySettingsService::productLabels($locale, ...)` (backend exports, PDF blade, validation messages, AI prompts) and `useProductLabels()` (frontend). Routes, permissions (`view-products`), and DB tables keep the `product` naming.
- Label-aware backend surfaces: product-performance Excel headings/title (`ProductPerformanceReportExport`), its PDF blade, `messages.product_category_delete_has_relations` (`:products` placeholder), `reports.products` fallback key, and AI chat (agent terminology rule + `:products` placeholder in `ai_chat.suggested`).
- **Feature flags.** The `features` object (`quotations_enabled/invoices_enabled`, each `boolean`) lets an admin switch the Quotations/Invoices modules on or off (e.g. real-estate brokers turn both off). Stored as `'1'`/`'0'` rows; a missing row means enabled (default ON, existing installs unaffected). When disabled, `QuotationPolicy`/`InvoicePolicy` deny every action with `messages.quotations_disabled` / `messages.invoices_disabled`, WhatsApp prepare and public signed downloads abort `403`, and the frontend hides the Sales Documents nav, route, tabs, and buttons via `useFeatures()` (`companySettings` Pinia store, default `true` pre-load).

---

## Policy

**File:** `app/Policies/CompanySettingPolicy.php`

```php
public function view(User $user): bool   // requires 'view-company-settings'
public function update(User $user): bool // requires 'update-company-settings'
```

Registered in `AppServiceProvider::boot()`:

```php
Gate::policy(CompanySetting::class, CompanySettingPolicy::class);
```

---

## Files Created

```
app/Models/CompanySetting.php
app/Policies/CompanySettingPolicy.php
app/Services/CompanySettingsService.php
app/Http/Requests/CompanySettings/UpdateCompanySettingsRequest.php
app/Http/Requests/CompanySettings/UploadCompanyLogoRequest.php
app/Http/Resources/CompanySettings/CompanySettingResource.php
app/Http/Controllers/Api/CompanySettingsController.php
database/migrations/2026_04_04_201610_create_company_settings_table.php
tests/Feature/CompanySettingsTest.php
tests/Feature/CompanySettingsAuthorizationTest.php
```

**Modified files:**

```
app/Providers/AppServiceProvider.php          — policy registration
database/seeders/RolesAndPermissionsSeeder.php — new permissions & role assignments
routes/api.php                                 — 4 new routes
lang/en/messages.php                           — company_logo_deleted key; :products placeholder in product_category_delete_has_relations
lang/ar/messages.php                           — company_logo_deleted key (Arabic); :products placeholder (Arabic)
lang/en/reports.php / lang/ar/reports.php      — reports.products plural fallback key
lang/en/ai_chat.php / lang/ar/ai_chat.php      — :products placeholder in suggested product question
app/Exports/ProductPerformanceReportExport.php — label-aware headings/title
resources/views/exports/reports/product-performance.blade.php — label-aware title/heading
app/Services/ProductCategoryService.php        — label-aware delete-guard message
app/Services/AiChatService.php                 — terminology passed to agent; placeholder replaced in suggested questions
app/AI/Agents/CrmAnalyticsAgent.php            — productSingular/productPlural + terminology rule
tests/Feature/ProductLabelsTest.php            — labels CRUD, validation, cache invalidation, export titles
tests/Pest.php                                 — forgetCache() in Feature beforeEach
```
