<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Webhook\StoreWebhookEndpointRequest;
use App\Http\Requests\Webhook\UpdateWebhookEndpointRequest;
use App\Http\Resources\Webhook\WebhookDeliveryResource;
use App\Http\Resources\Webhook\WebhookEndpointResource;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class WebhookEndpointController extends Controller
{
    public function __construct(private WebhookService $webhookService) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WebhookEndpoint::class);

        return WebhookEndpointResource::collection($this->webhookService->list());
    }

    public function store(StoreWebhookEndpointRequest $request): JsonResponse
    {
        $this->authorize('create', WebhookEndpoint::class);
        $generatedSecret = blank($request->input('secret'));
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $endpoint = $this->webhookService->create($data);

        $resource = new WebhookEndpointResource($endpoint);
        $resource->withSecret = $generatedSecret;

        return $resource->response()->setStatusCode(201);
    }

    public function show(WebhookEndpoint $webhook): WebhookEndpointResource
    {
        $this->authorize('view', $webhook);

        return new WebhookEndpointResource($webhook);
    }

    public function update(UpdateWebhookEndpointRequest $request, WebhookEndpoint $webhook): WebhookEndpointResource
    {
        $this->authorize('update', $webhook);
        $endpoint = $this->webhookService->update($webhook, $request->validated());

        return new WebhookEndpointResource($endpoint);
    }

    public function destroy(WebhookEndpoint $webhook): Response
    {
        $this->authorize('delete', $webhook);
        $this->webhookService->delete($webhook);

        return response()->noContent();
    }

    public function rotateSecret(WebhookEndpoint $webhook): WebhookEndpointResource
    {
        $this->authorize('update', $webhook);
        $endpoint = $this->webhookService->rotateSecret($webhook);

        $resource = new WebhookEndpointResource($endpoint);
        $resource->withSecret = true;

        return $resource;
    }

    public function deliveries(WebhookEndpoint $webhook): AnonymousResourceCollection
    {
        $this->authorize('view', $webhook);

        return WebhookDeliveryResource::collection($this->webhookService->deliveries($webhook));
    }

    public function redeliver(WebhookDelivery $delivery): Response
    {
        $this->authorize('update', $delivery->endpoint);
        $this->webhookService->redeliver($delivery);

        return response()->noContent();
    }
}
