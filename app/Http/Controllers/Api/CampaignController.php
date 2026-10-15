<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\ScheduleCampaignRequest;
use App\Http\Requests\Campaign\StoreCampaignRequest;
use App\Http\Requests\Campaign\UpdateCampaignRequest;
use App\Http\Resources\Campaign\CampaignRecipientResource;
use App\Http\Resources\Campaign\CampaignResource;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CampaignController extends Controller
{
    public function __construct(private CampaignService $campaignService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Campaign::class);
        return CampaignResource::collection($this->campaignService->list($request->all()));
    }

    public function store(StoreCampaignRequest $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('create', Campaign::class);
        $campaign = $this->campaignService->create($request->validated(), $request->user());
        return (new CampaignResource($campaign->load('template:id,name')))->response()->setStatusCode(201);
    }

    public function show(Campaign $campaign): CampaignResource
    {
        $this->authorize('view', $campaign);
        return new CampaignResource($campaign->load('template:id,name'));
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): CampaignResource
    {
        $this->authorize('update', $campaign);
        return new CampaignResource($this->campaignService->update($campaign, $request->validated()));
    }

    public function destroy(Campaign $campaign): JsonResponse
    {
        $this->authorize('delete', $campaign);
        $this->campaignService->delete($campaign);
        return response()->json(null, 204);
    }

    public function send(Campaign $campaign): CampaignResource
    {
        $this->authorize('send', $campaign);
        return new CampaignResource($this->campaignService->send($campaign)->load('template:id,name'));
    }

    public function schedule(ScheduleCampaignRequest $request, Campaign $campaign): CampaignResource
    {
        $this->authorize('update', $campaign);
        return new CampaignResource(
            $this->campaignService->schedule($campaign, $request->validated('scheduled_at'))->load('template:id,name')
        );
    }

    public function cancel(Campaign $campaign): CampaignResource
    {
        $this->authorize('cancel', $campaign);
        return new CampaignResource($this->campaignService->cancel($campaign)->load('template:id,name'));
    }

    public function recipients(Request $request, Campaign $campaign): AnonymousResourceCollection
    {
        $this->authorize('view', $campaign);
        $recipients = $this->campaignService->recipients($campaign, $request->all());
        return CampaignRecipientResource::collection($recipients);
    }
}
