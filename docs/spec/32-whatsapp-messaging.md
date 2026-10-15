# 32 — WhatsApp Messaging

Outbound WhatsApp messaging for customers, quotations, and invoices. Users review and can edit the recipient phone number and message before initiating a message. The default transport is WhatsApp Click-to-Chat; Meta WhatsApp Cloud API sending is optional.

## User Flow

1. From a customer or opportunity, the user opens the WhatsApp message modal.
2. The client prepares a localized default message and customer phone number through the API. Quotation and invoice messages include a temporary signed PDF download URL.
3. The user can edit the phone number and message. For quotation and invoice messages, the modal also links to the PDF.
4. By default, the browser opens a `wa.me` URL with the message prefilled. When Cloud API credentials are configured, the user may instead select backend sending.
5. The CRM records the outgoing message attempt as a completed `whatsapp` customer activity and, when sent from an opportunity, as an opportunity activity.

Click-to-Chat only opens WhatsApp; the CRM cannot determine whether the user sent the message or whether it was delivered. Cloud API success means Meta accepted the request, not that a recipient received or read it. Activities therefore represent initiation/acceptance, not confirmed delivery.

## API

All prepare and send routes require Sanctum authentication. Controllers authorize the customer and any nested opportunity or sales document. Preparing requires `view-customers`; sending also requires permission to create customer activities. No WhatsApp-specific permission is added.

| Method | URI | Purpose |
|---|---|---|
| `POST` | `/api/v1/customers/{customer}/whatsapp/prepare` | Prepare a general customer message |
| `POST` | `/api/v1/customers/{customer}/opportunities/{opportunity}/quotations/{quotation}/whatsapp/prepare` | Prepare a quotation message |
| `POST` | `/api/v1/customers/{customer}/opportunities/{opportunity}/invoice/{invoice}/whatsapp/prepare` | Prepare an invoice message |
| `POST` | `/api/v1/customers/{customer}/whatsapp/send` | Initiate/log a customer message |
| `POST` | `/api/v1/customers/{customer}/opportunities/{opportunity}/whatsapp/send` | Initiate/log an opportunity message |

Prepare accepts an optional `custom_message` string up to 2,000 characters. Send accepts `phone` (6–30 characters), `message` (1–4,000 characters), optional `subject` (up to 255 characters), and optional boolean `via_cloud_api`. Phone numbers are normalized to digits; values shorter than six digits after normalization are rejected. Nested customer, opportunity, quotation, and invoice relationships are checked before preparing or sending.

Preparation returns the message, normalized phone, recipient name, entity identifiers, optional document number/total/currency/PDF URL, a Click-to-Chat URL, and whether Cloud API sending is available. Send returns the dispatch method, phone, message, Click-to-Chat URL, optional Cloud API result, and logged activity.

### Public signed PDF downloads

The following routes do not require authentication, but Laravel validates the temporary URL signature and expiry:

| Method | URI | Purpose |
|---|---|---|
| `GET` | `/api/v1/public/quotations/{quotation}/download` | Download a quotation PDF |
| `GET` | `/api/v1/public/invoices/{invoice}/download` | Download an invoice PDF |

The WhatsApp preparation flow generates signed links with a seven-day lifetime. These links grant access to the corresponding PDF until expiry and should be treated as bearer links. Signed URLs embed `lang` (the sender's UI locale) inside the signature so guests see the PDF in that language.

## Delivery Configuration

Click-to-Chat is available without server-side WhatsApp credentials. Optional Cloud API configuration uses:

```dotenv
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_API_VERSION=v21.0
```

The service also recognizes `whatsapp_access_token` and `whatsapp_phone_number_id` values in company settings if they have been provisioned; there is no settings UI for these credentials. Cloud API sends text messages with URL previews, not media attachments, and uses Laravel's HTTP client, so no WhatsApp SDK dependency is required.

Meta's WhatsApp account configuration, messaging policies, and recipient eligibility still apply. The application does not currently implement inbound messages, delivery/read status webhooks, templates, or media upload.

## Frontend

- `resources/js/components/shared/WhatsAppSendModal.vue` provides the shared editable message flow and is used by customer and opportunity detail views.
- `resources/js/services/whatsappService.ts` calls the prepare and send endpoints through the shared API client.
- The component defaults to Click-to-Chat. It displays the Cloud API option only when the server reports that it is configured.
- After successful initiation, the caller refreshes the customer/opportunity activity and timeline views.

## Activity Logging

Outgoing messages use activity type `whatsapp`, with the edited message as the activity description and the chosen subject (or localized default) as the subject. The activity is marked completed with a positive outcome, and the customer's `last_contacted_at` is updated transactionally. Opportunity messages create a corresponding opportunity activity as well. Existing timeline aggregation surfaces these records through its activity events.

## Tests

The WhatsApp feature has dedicated coverage in `tests/Feature/WhatsAppTest.php`.