<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsApp\PrepareWhatsAppRequest;
use App\Http\Requests\WhatsApp\SendWhatsAppRequest;
use App\Http\Resources\WhatsApp\WhatsAppPreparedResource;
use App\Http\Resources\WhatsApp\WhatsAppSentResource;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\Quotation;
use App\Services\CompanySettingsService;
use App\Services\WhatsAppService;
use Illuminate\Validation\ValidationException;

class WhatsAppController extends Controller
{
    public function __construct(private WhatsAppService $whatsAppService) {}

    public function prepareCustomer(PrepareWhatsAppRequest $request, Customer $customer): WhatsAppPreparedResource
    {
        $this->authorize('view', $customer);

        return new WhatsAppPreparedResource(
            $this->whatsAppService->prepareCustomerMessage($customer, $request->validated('custom_message'))
        );
    }

    public function prepareQuotation(
        PrepareWhatsAppRequest $request,
        Customer $customer,
        Opportunity $opportunity,
        Quotation $quotation
    ): WhatsAppPreparedResource {
        $this->authorize('view', $customer);
        $this->authorize('view', $opportunity);
        $this->authorize('send', $quotation);
        abort_if(! CompanySettingsService::isQuotationsEnabled(), 403, __('messages.quotations_disabled'));
        abort_unless(
            $opportunity->customer_id === $customer->id
                && $quotation->customer_id === $customer->id
                && $quotation->opportunity_id === $opportunity->id,
            404
        );

        return new WhatsAppPreparedResource(
            $this->whatsAppService->prepareQuotationMessage($customer, $opportunity, $quotation)
        );
    }

    public function prepareInvoice(
        PrepareWhatsAppRequest $request,
        Customer $customer,
        Opportunity $opportunity,
        Invoice $invoice
    ): WhatsAppPreparedResource {
        $this->authorize('view', $customer);
        $this->authorize('view', $opportunity);
        $this->authorize('view', $invoice);
        abort_if(! CompanySettingsService::isInvoicesEnabled(), 403, __('messages.invoices_disabled'));
        abort_unless(
            $opportunity->customer_id === $customer->id
                && $invoice->customer_id === $customer->id
                && $invoice->opportunity_id === $opportunity->id,
            404
        );

        return new WhatsAppPreparedResource(
            $this->whatsAppService->prepareInvoiceMessage($customer, $opportunity, $invoice)
        );
    }

    public function sendCustomer(SendWhatsAppRequest $request, Customer $customer): WhatsAppSentResource
    {
        $this->authorize('view', $customer);
        $this->authorize('create', [CustomerActivity::class, $customer]);

        return $this->sendMessage($request, $customer, null);
    }

    public function sendOpportunity(
        SendWhatsAppRequest $request,
        Customer $customer,
        Opportunity $opportunity
    ): WhatsAppSentResource {
        $this->authorize('view', $customer);
        $this->authorize('view', $opportunity);
        $this->authorize('create', [CustomerActivity::class, $customer]);
        abort_unless($opportunity->customer_id === $customer->id, 404);

        return $this->sendMessage($request, $customer, $opportunity);
    }

    private function sendMessage(
        SendWhatsAppRequest $request,
        Customer $customer,
        ?Opportunity $opportunity
    ): WhatsAppSentResource {
        $validated = $request->validated();

        $phone = $this->whatsAppService->normalizePhoneNumber($validated['phone']);
        if (strlen($phone) < 6) {
            throw ValidationException::withMessages([
                'phone' => [__('messages.whatsapp_invalid_phone')],
            ]);
        }

        $message = $validated['message'];
        $subject = $validated['subject'] ?? null;
        $viaCloudApi = (bool) ($validated['via_cloud_api'] ?? false);

        $cloudApiResult = null;
        $dispatchedVia = 'click_to_chat';

        if ($viaCloudApi && ! $this->whatsAppService->isCloudApiConfigured()) {
            throw ValidationException::withMessages([
                'via_cloud_api' => [__('messages.whatsapp_send_failed')],
            ]);
        }

        if ($viaCloudApi) {
            $cloudApiResult = $this->whatsAppService->sendViaCloudApi($phone, $message);
            if (! $cloudApiResult['success']) {
                throw ValidationException::withMessages([
                    'message' => [$cloudApiResult['error'] ?? __('messages.whatsapp_send_failed')],
                ]);
            }

            $dispatchedVia = 'cloud_api';
        }

        $activity = $this->whatsAppService->logActivity(
            user: $request->user(),
            customer: $customer,
            message: $message,
            opportunity: $opportunity,
            subject: $subject
        );

        $result = [
            'success' => true,
            'dispatched_via' => $dispatchedVia,
            'phone' => $phone,
            'message' => $message,
            'click_to_chat_url' => $this->whatsAppService->buildClickToChatUrl($phone, $message),
            'cloud_api_result' => $cloudApiResult,
            'activity' => $activity,
        ];

        return new WhatsAppSentResource($result);
    }
}
