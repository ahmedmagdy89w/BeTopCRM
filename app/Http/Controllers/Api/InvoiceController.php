<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Invoice\InvoiceListResource;
use App\Http\Resources\Invoice\InvoiceResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Services\ArabicPdfService;
use App\Services\InvoiceService;
use App\Services\SalesDocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService,
        private SalesDocumentService $salesDocumentService,
    ) {}

    public function globalIndex(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Invoice::class);
        $invoices = $this->invoiceService->list($request->query());

        return InvoiceListResource::collection($invoices);
    }

    public function show(Customer $customer, Opportunity $opportunity): InvoiceResource
    {
        $invoice = $opportunity->invoice()->firstOrFail();
        $this->authorize('view', $invoice);
        $this->ensureBelongsTo($customer, $opportunity, $invoice);

        $invoice->load(['items', 'customer', 'quotation']);

        return new InvoiceResource($invoice, $this->salesDocumentService->forCustomer($invoice->customer));
    }

    public function pdf(Customer $customer, Opportunity $opportunity, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);
        $this->ensureBelongsTo($customer, $opportunity, $invoice);

        $invoice->load(['items', 'customer', 'opportunity']);
        $document = $this->salesDocumentService->forCustomer($invoice->customer);

        return ArabicPdfService::loadView('invoices.pdf', ['invoice' => $invoice, 'document' => $document])
            ->download($invoice->invoice_number.'.pdf');
    }

    public function preview(Customer $customer, Opportunity $opportunity, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);
        $this->ensureBelongsTo($customer, $opportunity, $invoice);

        $invoice->load(['items', 'customer', 'opportunity']);
        $document = $this->salesDocumentService->forCustomer($invoice->customer);

        return ArabicPdfService::loadView('invoices.pdf', ['invoice' => $invoice, 'document' => $document])
            ->stream($invoice->invoice_number.'.pdf');
    }

    public function store(Customer $customer, Opportunity $opportunity): InvoiceResource
    {
        $this->authorize('create', Invoice::class);
        $this->ensureOpportunityBelongsTo($customer, $opportunity);
        $invoice = $this->invoiceService->create($opportunity, request()->user());

        return new InvoiceResource($invoice, $this->salesDocumentService->forCustomer($invoice->customer));
    }

    private function ensureOpportunityBelongsTo(Customer $customer, Opportunity $opportunity): void
    {
        abort_if($opportunity->customer_id !== $customer->id, 404);
    }

    private function ensureBelongsTo(Customer $customer, Opportunity $opportunity, Invoice $invoice): void
    {
        $this->ensureOpportunityBelongsTo($customer, $opportunity);
        abort_if($invoice->opportunity_id !== $opportunity->id, 404);
    }
}
