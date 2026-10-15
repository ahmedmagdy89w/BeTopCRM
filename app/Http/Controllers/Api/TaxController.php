<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tax\StoreTaxRequest;
use App\Http\Requests\Tax\UpdateTaxRequest;
use App\Http\Resources\Tax\TaxResource;
use App\Models\Tax;
use App\Services\TaxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TaxController extends Controller
{
    public function __construct(private TaxService $taxService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Tax::class);
        $taxes = $this->taxService->list($request->boolean('active_only'));

        return TaxResource::collection($taxes);
    }

    public function store(StoreTaxRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $tax = $this->taxService->create($data);

        return (new TaxResource($tax))->response()->setStatusCode(201);
    }

    public function show(Tax $tax): TaxResource
    {
        $this->authorize('view', $tax);

        return new TaxResource($tax->loadCount('products'));
    }

    public function update(UpdateTaxRequest $request, Tax $tax): TaxResource
    {
        $this->authorize('update', $tax);

        return new TaxResource($this->taxService->update($tax, $request->validated()));
    }

    public function destroy(Tax $tax): Response
    {
        $this->authorize('delete', $tax);
        $this->taxService->delete($tax);

        return response()->noContent();
    }
}
