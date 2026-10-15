# 31 — AI Email Template Generator

AI-assisted generation of email campaign drafts. Given a topic, audience, tone, and language, the endpoint calls a language model and returns a ready-to-review `{ subject, body, placeholders }` payload. The draft is **never stored automatically** — the caller decides whether to save it via `POST /email-templates`.

---

## How It Works

1. Client sends a `POST /email-templates/generate` request with a plain-text description of the email.
2. `EmailTemplateGeneratorAgent` builds a system prompt from the requested `tone` and `language`, then sends the full prompt to the configured AI provider via `laravel/ai`.
3. The raw AI response is parsed — `SUBJECT:` and `BODY:` delimiters are extracted, and any `{{placeholder}}` tokens found in the text are catalogued.
4. The parsed draft is returned as JSON. No database record is written.

---

## AI Provider Configuration

| Environment | Provider | Model | Config key |
|---|---|---|---|
| Local (default) | Ollama (`http://localhost:11434`) | `gemma3:4b` | `AI_DEFAULT_PROVIDER=ollama` |
| Production | Gemini | `gemini-2.0-flash` | `AI_DEFAULT_PROVIDER=gemini` |

Relevant `.env` keys:

```dotenv
AI_DEFAULT_PROVIDER=ollama          # or: gemini
AI_MODEL=gemma3:4b                  # or: gemini-2.0-flash
GEMINI_API_KEY=your-key             # production only
```

The model name is read from `config('crm.ai.model')` (set in `config/crm.php`).

### Database override (settings UI)

An admin can configure the provider without redeploying via `PUT /api/v1/company/settings` (`ai` object — see [30-company-settings.md](30-company-settings.md)). `AiSettingsResolver` applies the stored `ai_provider` / `ai_model` / decrypted `ai_api_key` / `ai_base_url` to the runtime config before each `prompt()` call. **Precedence: database settings > environment variables.** When no DB values are stored, the `.env` behavior above applies unchanged.

---

## API Endpoint

| Method | URI | Controller action | Required permission |
|---|---|---|---|
| `POST` | `/api/v1/email-templates/generate` | `EmailTemplateController@generate` | `create-templates` |

Returns `201 Created` with an `AsyncJob` resource (`status: pending`). The AI call runs in the background via the queue. Poll `GET /api/v1/async-jobs/{id}` to retrieve the result once the job completes.

> The route is registered **before** the `apiResource` declaration to prevent Laravel from binding `generate` as a `{template}` route parameter.

---

## Permissions

Requires the `create-templates` permission. Any user (regardless of role) who holds this permission may call the endpoint. Users without it receive a `403` response.

See the role → permission matrix in [10-roles-permissions.md](10-roles-permissions.md) for which roles are granted `create-templates` by default.

---

## Request

**Form Request:** `app/Http/Requests/EmailTemplate/GenerateEmailTemplateRequest.php`

```php
public function rules(): array
{
    return [
        'topic'    => ['required', 'string', 'max:500'],
        'audience' => ['nullable', 'string', 'max:255'],
        'tone'     => ['nullable', 'string', 'in:professional,friendly,urgent'],
        'context'  => ['nullable', 'string', 'max:1000'],
        'language' => ['nullable', 'string', 'in:en,ar'],
    ];
}
```

### Field Reference

| Field | Type | Required | Default | Description |
|---|---|---|---|---|
| `topic` | `string` | ✅ | — | What the email is about. Max 500 chars. |
| `audience` | `string` | ❌ | `"our customers"` | Who the email targets (e.g. `"enterprise clients"`). |
| `tone` | `string` | ❌ | `professional` | One of: `professional`, `friendly`, `urgent`. |
| `context` | `string` | ❌ | `""` | Extra background the model should know. Max 1 000 chars. |
| `language` | `string` | ❌ | `en` | Output language: `en` (English) or `ar` (Arabic). |

### Example Request Body

```json
{
    "topic": "New product launch — Q3 2026",
    "audience": "enterprise clients",
    "tone": "professional",
    "language": "en",
    "context": "Focus on ROI and integration with existing workflows."
}
```

---

## Response

The endpoint returns `201 Created` immediately with a pending `AsyncJob`. The actual AI generation runs in the queue.

### Immediate response (`201 Created`)

```json
{
    "data": {
        "id": 42,
        "type": "App\\Services\\EmailTemplateService",
        "status": "pending",
        "progress": 0,
        "created_at": "2026-04-06T10:00:00Z",
        "updated_at": "2026-04-06T10:00:00Z"
    }
}
```

### Polling — `GET /api/v1/async-jobs/{id}` (completed)

```json
{
    "data": {
        "id": 42,
        "type": "App\\Services\\EmailTemplateService",
        "status": "completed",
        "progress": 100,
        "result": {
            "subject": "Introducing Our Latest Solution for {{company_name}}",
            "body": "Dear {{contact_name}},\n\nWe are excited to introduce our newest product...\n\nBest regards,\n{{account_manager_name}}",
            "placeholders": ["company_name", "contact_name", "account_manager_name"]
        },
        "started_at": "2026-04-06T10:00:01Z",
        "completed_at": "2026-04-06T10:00:04Z"
    }
}
```

### Polling — failed job

```json
{
    "data": {
        "id": 42,
        "status": "failed",
        "error": "AI generation failed. Please try again."
    }
}
```

See [02-api-endpoints.md](02-api-endpoints.md#async-jobs-polling) for all polling endpoints.

### Result fields

| Field | Type | Description |
|---|---|---|
| `subject` | `string` | Generated email subject line. May contain `{{placeholder}}` tokens. |
| `body` | `string` | Full email body, newline-separated. May contain `{{placeholder}}` tokens. |
| `placeholders` | `string[]` | Unique list of placeholder names extracted from both `subject` and `body`. |

---

## Error Responses

### 422 — Validation failure (before dispatch)

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "topic": ["The topic field is required."]
    }
}
```

| Trigger | Field | Message |
|---|---|---|
| `topic` missing | `topic` | required validation |
| `tone` not in allowed list | `tone` | `in:professional,friendly,urgent` |
| `language` not `en`/`ar` | `language` | `in:en,ar` |

### Job failure (AI provider error)

If the AI provider is unreachable or returns an error the job transitions to `failed` and the `error` field on the polled job contains:

```
"AI generation failed. Please try again."
```

### 401 / 403

Standard Sanctum / policy responses returned before the job is dispatched.

---

## Agent

**File:** `app/AI/Agents/EmailTemplateGeneratorAgent.php`

```php
class EmailTemplateGeneratorAgent implements Agent
{
    use Promptable;

    public function __construct(
        private string $tone,
        private string $languageName,
    ) {}

    public function instructions(): string
    {
        return <<<INSTRUCTIONS
        You are an expert email copywriter for a CRM system.
        Generate email templates with a {$this->tone} tone.
        Always write entirely in {$this->languageName}.
        Format your response exactly as:
        SUBJECT: <subject here>
        BODY:
        <body here>

        Use {{placeholder_name}} syntax for dynamic values like {{contact_name}}, {{company_name}}, {{deal_value}}.
        INSTRUCTIONS;
    }
}
```

`EmailTemplateGeneratorAgent::make(tone: $tone, languageName: $languageName)` — the `make()` static factory is provided by the `Promptable` trait.

---

## Service

**File:** `app/Services/EmailTemplateService.php`

### `generateWithAi(array $data): array`

| Step | Detail |
|---|---|
| Resolve language name | `'ar'` → `'Arabic'`, anything else → `'English'` |
| Resolve defaults | `tone` defaults to `professional`; `audience` defaults to `"our customers"` |
| Build agent | `EmailTemplateGeneratorAgent::make(tone:, languageName:)` |
| Call model | `$agent->prompt($promptText, model: $ai['model'])` where `$ai = AiSettingsResolver::applyToConfig()` (DB settings override env; falls back to `config('crm.ai.model')`) |
| Parse response | Extract `SUBJECT:` / `BODY:` blocks; extract `{{placeholders}}` |
| On failure | Wrap in `ValidationException` with `ai` key and `messages.ai_generation_failed` translation |

**Return shape:**

```php
[
    'subject'      => string,
    'body'         => string,
    'placeholders' => string[],   // unique, re-indexed
]
```

---

## Placeholder Syntax

Placeholders follow the same `{{name}}` convention used across all `EmailTemplate` records. The regex used for extraction is identical to the model's boot hook:

```php
preg_match_all('/\{\{(\w+)\}\}/', $subject . ' ' . $body, $matches);
```

Common placeholders the model is prompted to use:

| Placeholder | Meaning |
|---|---|
| `{{contact_name}}` | Recipient's full name |
| `{{company_name}}` | Recipient's company |
| `{{account_manager_name}}` | Sender / account manager |
| `{{deal_value}}` | Deal amount |
| `{{product_name}}` | Product or service name |

---

## Typical Client Workflow

```
1.  POST /api/v1/email-templates/generate   ← dispatch job, get { id, status: "pending" }
        ↓ poll every 2–3 seconds
2.  GET  /api/v1/async-jobs/{id}            ← check status; repeat until "completed" or "failed"
        ↓ review/edit draft in UI
3.  POST /api/v1/email-templates            ← save as a template
        ↓ attach template to a campaign
4.  POST /api/v1/campaigns                  ← create campaign
5.  POST /api/v1/campaigns/{id}/send        ← dispatch campaign
```

---

## Files

```
app/AI/Agents/EmailTemplateGeneratorAgent.php
app/Http/Requests/EmailTemplate/GenerateEmailTemplateRequest.php
tests/Feature/GenerateEmailTemplateTest.php
```

**Modified files:**

```
app/Services/EmailTemplateService.php          — generateWithAi() + parseAiResponse()
app/Http/Controllers/Api/EmailTemplateController.php  — generate() action
config/ai.php                                  — default provider via env
config/crm.php                                 — ai.model key
routes/api.php                                 — POST email-templates/generate
lang/en/messages.php                           — ai_generation_failed key
lang/ar/messages.php                           — ai_generation_failed key (Arabic)
```
