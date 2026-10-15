<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Opportunity\StoreOpportunityRequest;
use App\Http\Requests\Opportunity\UpdateOpportunityRequest;
use App\Http\Requests\Opportunity\UpdateOpportunityStageRequest;
use App\Http\Resources\Opportunity\OpportunityCollection;
use App\Http\Resources\Opportunity\OpportunityResource;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Services\OpportunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OpportunityController extends Controller
{
    public function __construct(private OpportunityService $opportunityService) {}

    public function index(Request $request, Customer $customer): OpportunityCollection
    {
        $this->authorize('viewAny', Opportunity::class);
        $opportunities = $this->opportunityService->list($customer->id, $request->query());

        return new OpportunityCollection($opportunities);
    }

    public function globalIndex(Request $request): OpportunityCollection
    {
        $this->authorize('viewAny', Opportunity::class);
        $opportunities = $this->opportunityService->list(null, $request->query());

        return new OpportunityCollection($opportunities);
    }

    public function store(StoreOpportunityRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('create', Opportunity::class);
        $opportunity = $this->opportunityService->create($customer->id, $request->validated(), $request->user());

        return (new OpportunityResource($opportunity->load('customer', 'assignee', 'creator')))->response()->setStatusCode(201);
    }

    public function show(Customer $customer, Opportunity $opportunity): OpportunityResource
    {
        $this->authorize('view', $opportunity);
        $this->ensureBelongsTo($customer, $opportunity);

        return new OpportunityResource($opportunity->load('customer', 'assignee', 'creator', 'tags', 'products.category')->loadCount('activities'));
    }

    public function update(UpdateOpportunityRequest $request, Customer $customer, Opportunity $opportunity): OpportunityResource
    {
        $this->authorize('update', $opportunity);
        $this->ensureBelongsTo($customer, $opportunity);
        $opportunity = $this->opportunityService->update($opportunity, $request->validated());

        return new OpportunityResource($opportunity->load('customer', 'assignee', 'creator'));
    }

    public function destroy(Customer $customer, Opportunity $opportunity): Response
    {
        $this->authorize('delete', $opportunity);
        $this->ensureBelongsTo($customer, $opportunity);
        $this->opportunityService->delete($opportunity);

        return response()->noContent();
    }

    public function updateStage(UpdateOpportunityStageRequest $request, Customer $customer, Opportunity $opportunity): OpportunityResource
    {
        $this->authorize('update', $opportunity);
        $this->ensureBelongsTo($customer, $opportunity);
        $opportunity = $this->opportunityService->updateStage($opportunity, $request->validated('stage'));

        return new OpportunityResource($opportunity->load('customer', 'assignee', 'creator'));
    }

    private function ensureBelongsTo(Customer $customer, Opportunity $opportunity): void
    {
        if ($opportunity->customer_id !== $customer->id) {
            abort(404, 'Opportunity does not belong to this customer.');
        }
    }
}
