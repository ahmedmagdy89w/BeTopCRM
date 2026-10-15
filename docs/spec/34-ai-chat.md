# 34 — AI Reporting & Analytics Chat

Stateless conversational assistant over live CRM aggregates. Owners ask reporting, analytics, and recommendation questions in English or Arabic; the endpoint grounds a language model in `ReportService` / `DashboardService` / `SearchService` output and returns an answer with insights, recommendations, and translated source labels. Nothing is persisted — each question is answered from a fresh snapshot and delivered through the standard async-job polling flow.

Uses the same provider setup as the email generator (see [31-ai-email-generator.md](31-ai-email-generator.md)): Ollama locally, Gemini in production, with `AiSettingsResolver` letting database settings override environment variables.

---

## How It Works

1. Client sends `POST /ai-chat/async` with a `question` (plus optional scope, date range, and language).
2. `AiChatController@chatAsync` authorizes, then dispatches `AiChatService::answerWithAi` through `AsyncJobService` (user ID is passed, not the model, so the payload survives JSON queue serialization).
3. `AiChatService` builds a compact, token-capped snapshot from existing services, then prompts `CrmAnalyticsAgent` via `laravel/ai` using the resolved provider/model.
4. The completed async job's `result` contains `{ answer, sources, locale, generated_at }`. The client polls `GET /api/v1/async-jobs/{id}` until `completed` or `failed`.
5. `GET /ai-chat/suggested-questions` returns localized "most asked" starter questions (no LLM call).

---

## AI Provider Configuration

Shared with [31-ai-email-generator.md](31-ai-email-generator.md). `AiSettingsResolver::applyToConfig()` applies stored `ai_provider` / `ai_model` / decrypted `ai_api_key` / `ai_base_url` to the runtime config before each `prompt()` call. **Precedence: database settings > environment variables.** The model falls back to `config('crm.ai.model')`.

---

## API Endpoints

| Method | URI | Controller action | Required permission |
|---|---|---|---|
| `POST` | `/api/v1/ai-chat/async` | `AiChatController@chatAsync` | `create-ai-chats` (throttled: 10/min) |
| `GET` | `/api/v1/ai-chat/suggested-questions` | `AiChatController@suggestedQuestions` | `create-ai-chats` |

Granted to `admin` and `manager` by `RolesAndPermissionsSeeder`; `sales_rep` is excluded. Routes additionally carry `permission:create-ai-chats` middleware, and every controller action calls `$this->authorize()` against `AiChatPolicy`.

---

## Request

**Form Request:** `app/Http/Requests/AiChat/ChatAiRequest.php`

`authorize()` returns `$this->user()->can('create', AiChat::class)` (resolved through `AiChatPolicy`).

```php
public function rules(): array
{
    return [
        'question' => ['required', 'string', 'max:1000'],
        'scope' => ['nullable', 'string', 'in:all,reports,pipeline,customers,products'],
        'user_id' => ['nullable', 'integer', 'exists:users,id'],
        'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        'customer_type' => ['nullable', 'string', 'in:individual,company'],
        'date_from' => ['nullable', 'date'],
        'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        'language' => ['nullable', 'string', 'in:en,ar'],
    ];
}
```

| Field | Type | Required | Default | Description |
|---|---|---|---|---|
| `question` | `string` | ✅ | — | The analytics question. Max 1 000 chars. Opportunity references are detected by ID (`opportunity 6`, `/customers/6/opportunities/6`, `الفرصة 6` incl. Arabic-Indic digits). |
| `scope` | `string` | ❌ | `all` | Narrows the snapshot: `all`, `reports`, `pipeline`, `customers`, `products`. |
| `user_id` | `integer` | ❌ | — | Scopes metrics to one user (`exists:users,id`). Mapped per section: `user_id` (user performance), `assigned_to` (lead pipeline + open opportunities), `account_manager_id` (customer report). A scope note with the user's name is appended to the prompt. |
| `customer_id` | `integer` | ❌ | — | Scopes metrics to one customer (`exists:customers,id`). Mapped per section: `customer_id` (lead pipeline + customer report). |
| `customer_type` | `string` | ❌ | — | `individual` or `company`; narrows the customer-report section. |
| `customer_id` | `integer` | ❌ | — | Scopes metrics to one customer (`exists:customers,id`). Mapped per section: `customer_id` (lead pipeline + customer report). A scope note with the customer's name is appended to the prompt. |
| `date_from`, `date_to` | `date` | ❌ | — | Forwarded to the report/dashboard aggregates. |
| `language` | `string` | ❌ | Active locale (`App::getLocale()`) | Answer language: `en` or `ar`. |

---

## Response

### Immediate response (`201 Created`)

```json
{
    "data": {
        "id": 42,
        "type": "App\\Services\\AiChatService",
        "status": "pending",
        "progress": 0,
        "created_at": "2026-09-30T10:00:00Z",
        "updated_at": "2026-09-30T10:00:00Z"
    }
}
```

### Polling — `GET /api/v1/async-jobs/{id}` (completed)

```json
{
    "data": {
        "id": 42,
        "type": "App\\Services\\AiChatService",
        "status": "completed",
        "progress": 100,
        "result": {
            "answer": "Conversion rate is 24% this month...\nالرؤى:\n...",
            "sources": ["lead_pipeline", "top_performers", "products"],
            "locale": "ar",
            "generated_at": "2026-09-30T10:00:04Z"
        },
        "started_at": "2026-09-30T10:00:01Z",
        "completed_at": "2026-09-30T10:00:04Z"
    }
}
```

`sources` are snapshot-section keys; the frontend maps them to localized labels (`aiChat.sourceLabels`). Failed jobs carry the translated `messages.ai_generation_failed` message in `error`.

### Suggested questions — `GET /api/v1/ai-chat/suggested-questions`

```json
{
    "data": {
        "questions": [
            "What is our lead conversion rate this month?",
            "Who are the top 5 performers by won value?",
            "..."
        ]
    }
}
```

Locale follows `App::getLocale()` (the `Accept-Language` header via `SetLocale` middleware), **not** the user's stored profile locale. Strings live in `lang/en/ai_chat.php` and `lang/ar/ai_chat.php`. The product-related starter question uses a `:products` placeholder (`Which :products drive booked sales…` / `ما هي :products التي تقود…`) which `AiChatService::suggestedQuestions()` replaces with the company's plural label for the requested locale (cached read, i18n fallback when unset).

---

## Snapshot (grounding context)

Built by `AiChatService::buildSnapshot()` from existing services — aggregates and capped detail rows only, never full table dumps or contact PII (search subtitles containing emails are stripped).

| Section | Content |
|---|---|
| `lead_pipeline` | `ReportService::leadPipeline()` summary + `by_status` + top-5 `by_source` (scope `all`/`reports`/`pipeline`) |
| `customers` | `ReportService::customerReport()` summary (scope `all`/`reports`/`customers`) |
| `user_performance` | `ReportService::userPerformance()` summary (scope `all`/`reports`/`pipeline`) |
| `top_performers` | First 5 user-performance rows (same scope as above) |
| `open_opportunities` | Up to 15 most-recently-updated **open** opportunities: `id`, `title`, `stage`, `amount`, `currency`, `customer` (same scope as above) |
| `products` | `ReportService::productPerformance()` summary + `top_products` (up to 8, with-sales first): `name`, `sku`, `category`, per-currency `booked_sales`, `open_pipeline` (scope `all`/`reports`/`products`) |
| `user_product_performance` | `ReportService::userProductPerformance()` summary + `top_rows` (up to 8 by booked value): `user`, `product`, `opportunities_count`, `won_count`, `booked_value_by_currency` (scope `all`/`reports`/`products`; honors the `user_id` filter) |
| `sales` | `ReportService::salesReport()` summary + latest 24 `periods`: `period`, `won_count`, `revenue_by_currency`, `open_count` (scope `all`/`reports`; honors the `user_id` filter; feeds forecasts) |
| `dashboard` | `DashboardService::index()` (role-scoped; scope `all`/`reports` only) |
| `search_hits` | Top-5 `SearchService` hits trimmed to `id/type/title/status`, plus stored `amount`/`currency` on opportunity hits (scope `all` only) |
| `referenced_opportunities` | Opportunities explicitly referenced in the question (Latin `opportunity N`, `/customers/{id}/opportunities/{id}` URLs, Arabic `الفرصة N` incl. Arabic-Indic digits; max 5): `id`, `title`, `stage`, `amount`, `currency`, `customer` (any scope) |

---

## Agent

**File:** `app/AI/Agents/CrmAnalyticsAgent.php`

```php
$agent = CrmAnalyticsAgent::make(
    languageName: $languageName,
    scope: $scope,
    productSingular: $productLabels['singular'],  // from CompanySettingsService::productLabels($locale, ...)
    productPlural: $productLabels['plural'],
);
$response = $agent->prompt($promptText, model: $ai['model']);
```

Strict rules (enforced in `instructions()`, headings localized — `الرؤى:` / `التوصيات:` for Arabic):

1. Answer ONLY from the snapshot JSON — never invent IDs, names, amounts, or dates.
2. Never sum across currencies; report grouped by ISO currency.
3. An opportunity's value is ONLY its `amount` — never a customer's `estimated_value` or a product `booked_value` total.
4. Summary totals are aggregates across many records — never quote them as one record's value.
5. For a specific record, use `referenced_opportunities` / `open_opportunities` / matching `search_hits`; if absent, say it was not found.
6. Structure: direct answer (2–5 sentences), insights bullets, recommendations bullets; ≤ 350 words, plain text.
7. REASONING OVER ROWS: ranking/comparison/composed questions (e.g. "top seller of the most sold product") must be answered by computing over snapshot rows (max/sum within one currency, cross-section joins) — never refused when inputs are present.
8. FORECASTS: revenue projections from `dashboard.revenue.by_month` run-rate, labeled as estimates with the method stated; true profit is impossible (no cost data) — project revenue and say so. Refuse only with no time-series data at all.
9. REPORTS CATALOG: the prompt embeds all seven reports (user-performance, company, customer, lead-pipeline, product-performance, user-product-performance, sales) with their contents and filters (`user_id`, `customer_id`, `customer_type`, `account_manager_id`, `assigned_to`, `product_id`, `category_id`, `status`, `source`, `min_score`, `health_below`, `industry`, `group_by`, dates) so the model answers from the right snapshot section and names the exact report + filter when data is missing instead of refusing generically.
7. TERMINOLOGY: the agent is told the company's catalog terms (`productSingular`/`productPlural`, resolved per answer language from Company Settings with i18n fallback) and must use them instead of "products" — and understand the user may use those terms when asking about the `products` snapshot section. The snapshot section key stays `products` (API contract, unchanged).

---

## Authorization

**Policy:** `app/Policies/AiChatPolicy.php` bound to the `App\AI\AiChat` value object in `AppServiceProvider`.

| Method | Rule |
|---|---|
| `create` | Has `create-ai-chats` (chat + async dispatch) |
| `viewAny` | Has `create-ai-chats` (suggested questions) |

---

## Error Responses

| Trigger | Response |
|---|---|
| Missing/invalid `question`, `scope`, `language`, `date_to < date_from` | `422` with field errors |
| Unauthenticated | `401` |
| Without `create-ai-chats` (e.g. `sales_rep`) | `403` (middleware + policy) |
| AI provider failure / >10 req/min | Failed job with translated error / `429` |

---

## Frontend

**Route:** `aiAssistant` (`/ai-assistant`), lazy-loaded, `meta: { permission: 'create-ai-chats' }`. Sidebar entry under Reports with the same permission gate.

| Piece | File | Notes |
|---|---|---|
| View | `resources/js/views/ai/AiChatView.vue` | `<script setup lang="ts">`; gradient hero, chat thread with avatars/timestamps/copy/clear/auto-scroll, side panel (suggested questions + scope/date/language filters), pill composer; logical CSS properties for RTL |
| Service | `resources/js/services/aiChatService.ts` | `askAsync(params): Promise<AsyncJob>`, `suggestedQuestions(): Promise<string[]>`; `AiChatScope`, `AiChatAnswer` types |
| Voice input | `AiChatView.vue` (Web Speech API) | Mic button (`SpeechRecognition`/`webkitSpeechRecognition`, hidden where unsupported); `continuous` mode until re-tapped, send disabled while listening, recognition follows the answer-language selector (`ar-SA`/`en-US`) |
| i18n | `resources/js/i18n/locales/{en,ar}.json` | `aiChat.*` (thread, composer, `sourceLabels`, voice keys), `nav.aiAssistant`, `roles.permissionLabels.create-ai-chats` + `view-assignable-users`, `roles.moduleLabels.ai-chats` + `assignable-users` |
| Permissions | `resources/js/utils/permissions.ts` | `CREATE_AI_CHATS: 'create-ai-chats'` |

---

## Files

```
app/AI/AiChat.php
app/AI/Agents/CrmAnalyticsAgent.php
app/Policies/AiChatPolicy.php
app/Services/AiChatService.php
app/Http/Requests/AiChat/ChatAiRequest.php
app/Http/Resources/AiChat/AiChatSuggestedQuestionsResource.php
app/Http/Controllers/Api/AiChatController.php
lang/en/ai_chat.php
lang/ar/ai_chat.php
resources/js/views/ai/AiChatView.vue
resources/js/services/aiChatService.ts
tests/Feature/AiChatTest.php
```

**Modified files:**

```
app/Providers/AppServiceProvider.php       — Gate::policy(AiChat::class, AiChatPolicy::class)
database/seeders/RolesAndPermissionsSeeder.php — create-ai-chats (admin + manager)
routes/api.php                             — ai-chat group (permission middleware + throttle:10,1 on POST)
resources/js/router/index.ts               — aiAssistant route
resources/js/components/layout/Sidebar.vue — Reports → AI Assistant item
resources/js/utils/permissions.ts          — CREATE_AI_CHATS
resources/js/i18n/locales/en.json|ar.json  — aiChat.*, nav.aiAssistant, roles labels
```
