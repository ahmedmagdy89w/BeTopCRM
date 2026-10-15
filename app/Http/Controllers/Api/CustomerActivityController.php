<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerActivity\CompleteCustomerActivityRequest;
use App\Http\Requests\CustomerActivity\StoreCustomerActivityRequest;
use App\Http\Requests\CustomerActivity\UpdateCustomerActivityRequest;
use App\Http\Resources\CustomerActivity\CustomerActivityCollection;
use App\Http\Resources\CustomerActivity\CustomerActivityResource;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Services\CustomerActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerActivityController extends Controller
{
    public function __construct(private CustomerActivityService $customerActivityService) {}

    public function index(Request $request, Customer $customer): CustomerActivityCollection
    {
        $this->authorize('viewAny', [CustomerActivity::class, $customer]);
        $activities = $this->customerActivityService->list($customer, $request->query());

        return new CustomerActivityCollection($activities);
    }

    public function globalIndex(Request $request): CustomerActivityCollection
    {
        $this->authorize('viewAny', CustomerActivity::class);
        $activities = $this->customerActivityService->globalList($request->query(), $request->user());

        return new CustomerActivityCollection($activities);
    }

    public function store(StoreCustomerActivityRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('create', [CustomerActivity::class, $customer]);
        $activity = $this->customerActivityService->create($customer, $request->validated(), $request->user());

        return (new CustomerActivityResource($activity->load('customer', 'user')))->response()->setStatusCode(201);
    }

    public function update(UpdateCustomerActivityRequest $request, Customer $customer, CustomerActivity $activity): CustomerActivityResource
    {
        $this->authorize('update', $activity);
        $activity = $this->customerActivityService->update($activity, $request->validated());

        return new CustomerActivityResource($activity->load('customer', 'user'));
    }

    public function destroy(Customer $customer, CustomerActivity $activity): Response
    {
        $this->authorize('delete', $activity);
        $this->customerActivityService->delete($activity);

        return response()->noContent();
    }

    public function complete(CompleteCustomerActivityRequest $request, Customer $customer, CustomerActivity $activity): CustomerActivityResource
    {
        $this->authorize('complete', $activity);
        $activity = $this->customerActivityService->complete($activity, $request->validated('outcome'));

        return new CustomerActivityResource($activity->load('customer', 'user'));
    }
}
