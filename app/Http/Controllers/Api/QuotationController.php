<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\SendQuotationRequest;
use App\Http\Requests\Quotation\StoreQuotationRequest;
use App\Http\Resources\Quotation\QuotationListResource;
use App\Http\Resources\Quotation\QuotationResource;
use App\Mail\QuotationMail;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Quotation;
use App\Services\ArabicPdfService;
use App\Services\QuotationService;
use App\Services\SalesDocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

class QuotationController extends Controller
{
    public function __construct(
        private QuotationService $quotationService,
        private SalesDocumentService $salesDocumentService,
    ) {}

    public function globalIndex(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Quotation::class);
        $quotations = $this->quotationService->list($request->query());

        return QuotationListResource::collection($quotations);
    }

    public function index(Customer $customer, Opportunity $opportunity): mixed
    {
        $this->authorize('viewAny', Quotation::class);
        $this->ensureBelongsTo($customer, $opportunity);

        $quotations = $opportunity->quotations()->with(['items', 'customer'])->latest()->get();
        $company = $this->salesDocumentService->company();

        return QuotationResource::collection($quotations->map(fn (Quotation $quotation) => new QuotationResource($quotation, [
            'company' => $company,
            'buyer' => $this->salesDocumentService->buyer($quotation->customer),
        ])));
    }

    public function store(StoreQuotationRequest $request, Customer $customer, Opportunity $opportunity): QuotationResource
    {
        $this->authorize('create', Quotation::class);
        $this->ensureBelongsTo($customer, $opportunity);
        $quotation = $this->quotationService->create($opportunity, $request->validated(), $request->user());

        return new QuotationResource($quotation, $this->salesDocumentService->forCustomer($quotation->customer));
    }

    public function show(Customer $customer, Opportunity $opportunity, Quotation $quotation): QuotationResource
    {
        $this->authorize('view', $quotation);
        $this->ensureBelongsTo($customer, $opportunity);
        $this->ensureQuotationBelongsTo($opportunity, $quotation);
        $quotation->load(['items', 'customer', 'opportunity']);

        return new QuotationResource($quotation, $this->salesDocumentService->forCustomer($quotation->customer));
    }

    public function pdf(Customer $customer, Opportunity $opportunity, Quotation $quotation): Response
    {
        $this->authorize('view', $quotation);
        $this->ensureBelongsTo($customer, $opportunity);
        $this->ensureQuotationBelongsTo($opportunity, $quotation);
        $quotation->load(['items', 'customer', 'opportunity']);
        $document = $this->salesDocumentService->forCustomer($quotation->customer);

        return ArabicPdfService::loadView('quotations.pdf', ['quotation' => $quotation, 'document' => $document])
            ->download($quotation->quotation_number.'.pdf');
    }

    public function preview(Customer $customer, Opportunity $opportunity, Quotation $quotation): Response
    {
        $this->authorize('view', $quotation);
        $this->ensureBelongsTo($customer, $opportunity);
        $this->ensureQuotationBelongsTo($opportunity, $quotation);
        $quotation->load(['items', 'customer', 'opportunity']);
        $document = $this->salesDocumentService->forCustomer($quotation->customer);

        return ArabicPdfService::loadView('quotations.pdf', ['quotation' => $quotation, 'document' => $document])
            ->stream($quotation->quotation_number.'.pdf');
    }

    public function send(SendQuotationRequest $request, Customer $customer, Opportunity $opportunity, Quotation $quotation): QuotationResource
    {
        $this->authorize('send', $quotation);
        $this->ensureBelongsTo($customer, $opportunity);
        $this->ensureQuotationBelongsTo($opportunity, $quotation);

        if ($quotation->status === 'sent') {
            $quotation->load(['items', 'customer', 'opportunity']);

            return new QuotationResource($quotation, $this->salesDocumentService->forCustomer($quotation->customer));
        }

        $quotation = $this->quotationService->send($quotation->load(['items', 'customer', 'opportunity']), $request->user());
        $document = $this->salesDocumentService->forCustomer($quotation->customer);
        Mail::to($quotation->customer->email ?: $quotation->customer->contact_email)
            ->queue(new QuotationMail($quotation, $document));

        return new QuotationResource($quotation, $document);
    }

    private function ensureBelongsTo(Customer $customer, Opportunity $opportunity): void
    {
        abort_if($opportunity->customer_id !== $customer->id, 404);
    }

    private function ensureQuotationBelongsTo(Opportunity $opportunity, Quotation $quotation): void
    {
        abort_if($quotation->opportunity_id !== $opportunity->id, 404);
    }
}
