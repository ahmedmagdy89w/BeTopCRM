<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\OpportunityActivity;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class WhatsAppService
{
    public function __construct(
        private SalesDocumentService $salesDocumentService
    ) {}

    /**
     * Clean and normalize a phone number into an international numeric string for WhatsApp.
     */
    public function normalizePhoneNumber(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        // Remove all non-digit characters
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        // If starts with 00, remove the leading zeros
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    /**
     * Extract the best usable phone number for a customer.
     */
    public function getCustomerPhoneNumber(Customer $customer): string
    {
        $primaryContact = $this->primaryContactRecord($customer->contacts ?? []);

        $candidate = $this->firstFilled([
            $primaryContact['phone'] ?? null,
            $customer->phone,
            $customer->contact_phone,
        ]);

        return $this->normalizePhoneNumber($candidate);
    }

    /**
     * Build temporary signed PDF download URL for a quotation.
     */
    public function quotationSignedUrl(Quotation $quotation, int $validDays = 7): string
    {
        return URL::temporarySignedRoute(
            'public.quotations.download',
            now()->addDays($validDays),
            ['quotation' => $quotation->id, 'lang' => App::getLocale()]
        );
    }

    /**
     * Build temporary signed PDF download URL for an invoice.
     */
    public function invoiceSignedUrl(Invoice $invoice, int $validDays = 7): string
    {
        return URL::temporarySignedRoute(
            'public.invoices.download',
            now()->addDays($validDays),
            ['invoice' => $invoice->id, 'lang' => App::getLocale()]
        );
    }

    /**
     * Build Click-to-Chat URL for wa.me.
     */
    public function buildClickToChatUrl(string $phone, string $message): string
    {
        $normalized = $this->normalizePhoneNumber($phone);

        return 'https://wa.me/'.$normalized.'?text='.rawurlencode($message);
    }

    /**
     * Prepare quotation WhatsApp message details.
     */
    public function prepareQuotationMessage(Customer $customer, Opportunity $opportunity, Quotation $quotation): array
    {
        $quotation->loadMissing(['items', 'customer', 'opportunity']);
        $phone = $this->getCustomerPhoneNumber($customer);
        $signedUrl = $this->quotationSignedUrl($quotation);
        $company = $this->salesDocumentService->company();
        $companyName = $company['company_name'] ?? config('app.name', 'CRM');

        $recipientName = $customer->display_name;
        $totalFormatted = number_format((float) $quotation->total, 2).' '.$quotation->currency;

        $lines = [
            __('messages.whatsapp_quotation_greeting', ['name' => $recipientName]),
            '',
            __('messages.whatsapp_quotation_intro', [
                'company' => $companyName,
                'number' => $quotation->quotation_number,
                'total' => $totalFormatted,
            ]),
        ];

        if ($quotation->valid_until) {
            $lines[] = __('messages.whatsapp_quotation_validity', [
                'date' => $quotation->valid_until->format('Y-m-d'),
            ]);
        }

        $lines[] = '';
        $lines[] = __('messages.whatsapp_download_pdf', ['url' => $signedUrl]);
        $lines[] = '';
        $lines[] = __('messages.whatsapp_closing', ['company' => $companyName]);

        $message = implode("\n", $lines);

        return [
            'entity_type' => 'quotation',
            'entity_id' => $quotation->id,
            'customer_id' => $customer->id,
            'opportunity_id' => $opportunity->id,
            'phone' => $phone,
            'recipient_name' => $recipientName,
            'document_number' => $quotation->quotation_number,
            'total' => (float) $quotation->total,
            'currency' => $quotation->currency,
            'pdf_url' => $signedUrl,
            'message' => $message,
            'click_to_chat_url' => $this->buildClickToChatUrl($phone, $message),
            'cloud_api_available' => $this->isCloudApiConfigured(),
        ];
    }

    /**
     * Prepare invoice WhatsApp message details.
     */
    public function prepareInvoiceMessage(Customer $customer, Opportunity $opportunity, Invoice $invoice): array
    {
        $invoice->loadMissing(['items', 'customer', 'opportunity']);
        $phone = $this->getCustomerPhoneNumber($customer);
        $signedUrl = $this->invoiceSignedUrl($invoice);
        $company = $this->salesDocumentService->company();
        $companyName = $company['company_name'] ?? config('app.name', 'CRM');

        $recipientName = $customer->display_name;
        $totalFormatted = number_format((float) $invoice->total, 2).' '.$invoice->currency;

        $lines = [
            __('messages.whatsapp_invoice_greeting', ['name' => $recipientName]),
            '',
            __('messages.whatsapp_invoice_intro', [
                'company' => $companyName,
                'number' => $invoice->invoice_number,
                'total' => $totalFormatted,
            ]),
        ];

        $lines[] = '';
        $lines[] = __('messages.whatsapp_download_pdf', ['url' => $signedUrl]);
        $lines[] = '';
        $lines[] = __('messages.whatsapp_closing', ['company' => $companyName]);

        $message = implode("\n", $lines);

        return [
            'entity_type' => 'invoice',
            'entity_id' => $invoice->id,
            'customer_id' => $customer->id,
            'opportunity_id' => $opportunity->id,
            'phone' => $phone,
            'recipient_name' => $recipientName,
            'document_number' => $invoice->invoice_number,
            'total' => (float) $invoice->total,
            'currency' => $invoice->currency,
            'pdf_url' => $signedUrl,
            'message' => $message,
            'click_to_chat_url' => $this->buildClickToChatUrl($phone, $message),
            'cloud_api_available' => $this->isCloudApiConfigured(),
        ];
    }

    /**
     * Prepare direct message details for a customer.
     */
    public function prepareCustomerMessage(Customer $customer, ?string $customMessage = null): array
    {
        $phone = $this->getCustomerPhoneNumber($customer);
        $company = $this->salesDocumentService->company();
        $companyName = $company['company_name'] ?? config('app.name', 'CRM');
        $recipientName = $customer->display_name;

        $message = $customMessage ?: implode("\n", [
            __('messages.whatsapp_general_greeting', ['name' => $recipientName]),
            '',
            __('messages.whatsapp_general_body'),
            '',
            __('messages.whatsapp_closing', ['company' => $companyName]),
        ]);

        return [
            'entity_type' => 'customer',
            'entity_id' => $customer->id,
            'customer_id' => $customer->id,
            'opportunity_id' => null,
            'phone' => $phone,
            'recipient_name' => $recipientName,
            'document_number' => null,
            'total' => null,
            'currency' => null,
            'pdf_url' => null,
            'message' => $message,
            'click_to_chat_url' => $this->buildClickToChatUrl($phone, $message),
            'cloud_api_available' => $this->isCloudApiConfigured(),
        ];
    }

    /**
     * Record a sent WhatsApp message as an activity in Customer & Opportunity.
     */
    public function logActivity(
        User $user,
        Customer $customer,
        string $message,
        ?Opportunity $opportunity = null,
        ?string $subject = null
    ): CustomerActivity {
        $subject = $subject ?: __('messages.whatsapp_activity_subject', ['recipient' => $customer->display_name]);

        return DB::transaction(function () use ($user, $customer, $message, $opportunity, $subject): CustomerActivity {
            $customerActivity = CustomerActivity::create([
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'type' => 'whatsapp',
                'channel' => 'whatsapp',
                'subject' => Str::limit($subject, 255),
                'description' => $message,
                'outcome' => 'positive',
                'due_date' => now(),
                'completed_at' => now(),
            ]);

            $customer->update(['last_contacted_at' => now()]);

            if ($opportunity) {
                OpportunityActivity::create([
                    'opportunity_id' => $opportunity->id,
                    'user_id' => $user->id,
                    'type' => 'whatsapp',
                    'subject' => Str::limit($subject, 255),
                    'description' => $message,
                    'outcome' => 'positive',
                    'due_date' => now(),
                    'completed_at' => now(),
                ]);
            }

            return $customerActivity;
        });
    }

    /**
     * Check if Meta WhatsApp Cloud API credentials are provided.
     */
    public function isCloudApiConfigured(): bool
    {
        $settings = CompanySetting::cachedAll();

        $token = $settings['whatsapp_access_token'] ?? config('services.whatsapp.access_token');
        $phoneId = $settings['whatsapp_phone_number_id'] ?? config('services.whatsapp.phone_number_id');

        return ! empty($token) && ! empty($phoneId);
    }

    /**
     * Send message using Meta WhatsApp Cloud API if configured.
     */
    public function sendViaCloudApi(string $phone, string $message): array
    {
        $settings = CompanySetting::cachedAll();
        $token = $settings['whatsapp_access_token'] ?? config('services.whatsapp.access_token');
        $phoneId = $settings['whatsapp_phone_number_id'] ?? config('services.whatsapp.phone_number_id');
        $apiVersion = config('services.whatsapp.api_version', 'v21.0');

        if (empty($token) || empty($phoneId)) {
            return [
                'success' => false,
                'error' => 'WhatsApp Cloud API credentials not configured.',
            ];
        }

        $recipientPhone = $this->normalizePhoneNumber($phone);
        $url = "https://graph.facebook.com/{$apiVersion}/{$phoneId}/messages";

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $recipientPhone,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => true,
                        'body' => $message,
                    ],
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('error.message') ?? 'Failed to send WhatsApp message via Cloud API',
                'status' => $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function primaryContactRecord(array $records): array
    {
        foreach ($records as $record) {
            if (($record['is_primary'] ?? false) === true) {
                return $record;
            }
        }

        return $records[0] ?? [];
    }

    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
