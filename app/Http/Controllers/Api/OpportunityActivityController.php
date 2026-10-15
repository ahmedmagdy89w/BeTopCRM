<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OpportunityActivity\CompleteOpportunityActivityRequest;
use App\Http\Requests\OpportunityActivity\StoreOpportunityActivityRequest;
use App\Http\Requests\OpportunityActivity\UpdateOpportunityActivityRequest;
use App\Http\Resources\OpportunityActivity\OpportunityActivityCollection;
use App\Http\Resources\OpportunityActivity\OpportunityActivityResource;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\OpportunityActivity;
use App\Services\OpportunityActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OpportunityActivityController extends Controller
{
    public function __construct(private OpportunityActivityService $opportunityActivityService) {}

    public function index(Request $request, Customer $customer, Opportunity $opportunity): OpportunityActivityCollection
    {
        $this->authorize('view', $opportunity);
        $this->ensureBelongsTo($customer, $opportunity);
        $activities = $this->opportunityActivityService->list($opportunity, $request->query());

        return new OpportunityActivityCollection($activities);
    }

    public function store(StoreOpportunityActivityRequest $request, Customer $customer, Opportunity $opportunity): JsonResponse
    {
        $this->authorize('update', $opportunity);
        $this->ensureBelongsTo($customer, $opportunity);
        $activity = $this->opportunityActivityService->create($opportunity, $request->validated(), $request->user());

        return (new OpportunityActivityResource($activity))->response()->setStatusCode(201);
    }

    public function update(UpdateOpportunityActivityRequest $request, Customer $customer, Opportunity $opportunity, OpportunityActivity $activity): OpportunityActivityResource
    {
        $this->authorize('update', $opportunity);
        $this->ensureBelongsTo($customer, $opportunity);
        $this->ensureActivityBelongsTo($opportunity, $activity);
        $activity = $this->opportunityActivityService->update($activity, $request->validated());

        return new OpportunityActivityResource($activity);
    }

    public function destroy(Customer $customer, Opportunity $opportunity, OpportunityActivity $activity): Response
    {
        $this->authorize('update', $opportunity);
        $this->ensureBelongsTo($customer, $opportunity);
        $this->ensureActivityBelongsTo($opportunity, $activity);
        $this->opportunityActivityService->delete($activity);

        return response()->noContent();
    }

    public function complete(CompleteOpportunityActivityRequest $request, Customer $customer, Opportunity $opportunity, OpportunityActivity $activity): OpportunityActivityResource
    {
        $this->authorize('update', $opportunity);
        $this->ensureBelongsTo($customer, $opportunity);
        $this->ensureActivityBelongsTo($opportunity, $activity);
        $activity = $this->opportunityActivityService->complete($activity, $request->validated('outcome'));

        return new OpportunityActivityResource($activity);
    }

    private function ensureBelongsTo(Customer $customer, Opportunity $opportunity): void
    {
        if ($opportunity->customer_id !== $customer->id) {
            abort(404, 'Opportunity does not belong to this customer.');
        }
    }

    private function ensureActivityBelongsTo(Opportunity $opportunity, OpportunityActivity $activity): void
    {
        if ($activity->opportunity_id !== $opportunity->id) {
            abort(404, 'Activity does not belong to this opportunity.');
        }
    }
}
