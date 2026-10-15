<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\AssignCustomerRequest;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerLeadStatusRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Requests\Tag\SyncTagsRequest;
use App\Http\Resources\Customer\CustomerCollection;
use App\Http\Resources\Customer\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Services\LeadScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customerService,
        private LeadScoringService $leadScoringService,
    ) {}

    public function index(Request $request): CustomerCollection
    {
        $this->authorize('viewAny', Customer::class);
        $customers = $this->customerService->list($request->query());

        return new CustomerCollection($customers);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);
        $customer = $this->customerService->create($request->validated(), $request->user());

        return (new CustomerResource($customer->load('assignee', 'creator', 'accountManager', 'tags')))->response()->setStatusCode(201);
    }

    public function show(Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        return new CustomerResource(
            $customer->load('assignee', 'creator', 'accountManager', 'tags', 'opportunities')->loadCount(['activities', 'opportunities'])
        );
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerResource
    {
        $this->authorize('update', $customer);
        $customer = $this->customerService->update($customer, $request->validated());

        return new CustomerResource($customer->load('assignee', 'creator', 'accountManager', 'tags'));
    }

    public function destroy(Customer $customer): Response
    {
        $this->authorize('delete', $customer);
        $this->customerService->delete($customer);

        return response()->noContent();
    }

    public function updateLeadStatus(UpdateCustomerLeadStatusRequest $request, Customer $customer): CustomerResource
    {
        $this->authorize('update', $customer);
        $customer = $this->customerService->updateLeadStatus($customer, $request->validated('lead_status'));

        return new CustomerResource($customer->load('assignee', 'creator', 'accountManager'));
    }

    public function assign(AssignCustomerRequest $request, Customer $customer): CustomerResource
    {
        $this->authorize('update', $customer);
        $customer = $this->customerService->assign($customer, $request->validated('assigned_to'));

        return new CustomerResource($customer->load('assignee', 'creator', 'accountManager'));
    }

    public function convert(Customer $customer): CustomerResource
    {
        $this->authorize('update', $customer);
        $customer = $this->customerService->convertToCustomer($customer);

        return new CustomerResource($customer->load('assignee', 'accountManager'));
    }

    public function syncTags(SyncTagsRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);
        $customer->syncTags($request->validated('tag_ids'));

        return response()->json(['message' => 'Tags synced.']);
    }

    public function score(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);
        if ($customer->scored_at === null) {
            $this->leadScoringService->calculate($customer);
            $customer->refresh();
        }

        return response()->json([
            'data' => [
                'score' => $customer->score,
                'breakdown' => $customer->score_breakdown,
                'scored_at' => $customer->scored_at,
            ],
        ]);
    }

    public function recalculateScore(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);
        $score = $this->leadScoringService->calculate($customer);
        $customer->refresh();

        return response()->json([
            'data' => [
                'score' => $score,
                'breakdown' => $customer->score_breakdown,
                'scored_at' => $customer->scored_at,
            ],
        ]);
    }
}
