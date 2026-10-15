<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Note\StoreNoteRequest;
use App\Http\Requests\Note\UpdateNoteRequest;
use App\Http\Resources\Note\NoteResource;
use App\Models\Note;
use App\Services\NoteService;
use App\Services\TimelineService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class NoteController extends Controller
{
    public function __construct(private NoteService $noteService) {}

    public function index(string $entityType, int $entityId): AnonymousResourceCollection
    {
        $entity = $this->resolveEntity($entityType, $entityId);
        $this->authorizeEntityView($entity);

        return NoteResource::collection(
            $this->noteService->listForEntity($entity, request()->all())
        );
    }

    public function store(StoreNoteRequest $request, string $entityType, int $entityId): JsonResponse
    {
        $entity = $this->resolveEntity($entityType, $entityId);
        $this->authorizeEntityView($entity);

        $note = $this->noteService->create($entity, $request->validated(), $request->user());

        return (new NoteResource($note))->response()->setStatusCode(201);
    }

    public function update(UpdateNoteRequest $request, Note $note): NoteResource
    {
        $note = $this->noteService->update($note, $request->validated());

        return new NoteResource($note);
    }

    public function destroy(Note $note): Response
    {
        $this->authorize('delete', $note);
        $this->noteService->delete($note);

        return response()->noContent();
    }

    public function togglePin(Note $note): NoteResource
    {
        $this->authorize('togglePin', $note);
        $note = $this->noteService->togglePin($note);

        return new NoteResource($note);
    }

    private function resolveEntity(string $entityType, int $entityId): Model
    {
        $map = TimelineService::ENTITY_MAP;

        if (! isset($map[$entityType])) {
            abort(404, 'Unknown entity type.');
        }

        return $map[$entityType]::findOrFail($entityId);
    }

    private function authorizeEntityView(Model $entity): void
    {
        $this->authorize('viewAny', [Note::class, $entity]);
    }
}
