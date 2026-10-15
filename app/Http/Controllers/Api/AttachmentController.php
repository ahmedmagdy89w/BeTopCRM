<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attachment\StoreAttachmentRequest;
use App\Http\Resources\Attachment\AttachmentResource;
use App\Models\Attachment;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Services\AttachmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AttachmentController extends Controller
{
    private array $entityMap = [
        'customers' => Customer::class,
        'opportunities' => Opportunity::class,
    ];

    public function __construct(private AttachmentService $attachmentService) {}

    public function index(Request $request, string $entityType, int $entityId): AnonymousResourceCollection
    {
        $entity = $this->resolveEntity($entityType, $entityId);
        $this->authorize('view', $entity);
        $attachments = $this->attachmentService->list($entity);

        return AttachmentResource::collection($attachments);
    }

    public function store(StoreAttachmentRequest $request, string $entityType, int $entityId): AnonymousResourceCollection
    {
        $entity = $this->resolveEntity($entityType, $entityId);
        $this->authorize('update', $entity);
        $this->authorize('create', Attachment::class);
        $attachments = $this->attachmentService->store($entity, $request->file('files'), $request->user());
        $ids = collect($attachments)->pluck('id');
        $loaded = Attachment::whereIn('id', $ids)->with('uploader:id,name')->get();

        return AttachmentResource::collection($loaded);
    }

    public function show(Attachment $attachment): AttachmentResource
    {
        $this->authorize('download', $attachment);

        return new AttachmentResource($attachment->load('uploader:id,name'));
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        $this->authorize('download', $attachment);

        return $this->attachmentService->download($attachment);
    }

    public function destroy(Attachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);
        $this->attachmentService->delete($attachment);

        return response()->json(null, 204);
    }

    private function resolveEntity(string $entityType, int $entityId): Model
    {
        $modelClass = $this->entityMap[$entityType] ?? null;

        if (! $modelClass) {
            throw new NotFoundHttpException("Unknown entity type: {$entityType}");
        }

        return $modelClass::findOrFail($entityId);
    }
}
