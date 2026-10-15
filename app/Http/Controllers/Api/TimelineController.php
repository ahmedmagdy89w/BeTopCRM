<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Timeline\TimelineEventResource;
use App\Services\TimelineService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TimelineController extends Controller
{
    public function __construct(private TimelineService $timelineService) {}

    public function index(string $entityType, int $entityId, Request $request): AnonymousResourceCollection
    {
        $map = TimelineService::ENTITY_MAP;

        if (! isset($map[$entityType])) {
            abort(404, 'Unknown entity type.');
        }

        /** @var Model $entity */
        $entity = $map[$entityType]::findOrFail($entityId);
        $this->authorize('view', $entity);
        $this->authorize('view-timeline');

        $paginator = $this->timelineService->forEntity($entityType, $entityId, $request->all());

        return TimelineEventResource::collection($paginator);
    }
}
