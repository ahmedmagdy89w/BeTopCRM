<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Services\ArabicPdfService;
use App\Services\CompanySettingsService;
use App\Services\SalesDocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicDocumentController extends Controller
{
    public function __construct(private SalesDocumentService $salesDocumentService) {}

    public function downloadQuotation(Request $request, Quotation $quotation): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403, __('messages.invalid_signature'));
        }

        abort_if(! CompanySettingsService::isQuotationsEnabled(), 403, __('messages.quotations_disabled'));

        $quotation->load(['items', 'customer', 'opportunity']);
        $document = $this->salesDocumentService->forCustomer($quotation->customer);

        return ArabicPdfService::loadView('quotations.pdf', [
            'quotation' => $quotation,
            'document' => $document,
        ])->download($quotation->quotation_number.'.pdf');
    }

    public function downloadInvoice(Request $request, Invoice $invoice): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403, __('messages.invalid_signature'));
        }

        abort_if(! CompanySettingsService::isInvoicesEnabled(), 403, __('messages.invoices_disabled'));

        $invoice->load(['items', 'customer', 'opportunity']);
        $document = $this->salesDocumentService->forCustomer($invoice->customer);

        return ArabicPdfService::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'document' => $document,
        ])->download($invoice->invoice_number.'.pdf');
    }
}
