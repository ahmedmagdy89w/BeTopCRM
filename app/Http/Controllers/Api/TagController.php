<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tag\StoreTagRequest;
use App\Http\Requests\Tag\UpdateTagRequest;
use App\Http\Resources\Tag\TagResource;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    public function __construct(private TagService $tagService) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Tag::class);
        return TagResource::collection($this->tagService->list());
    }

    public function store(StoreTagRequest $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('create', Tag::class);
        $tag = $this->tagService->create($request->validated(), $request->user());
        return (new TagResource($tag))->response()->setStatusCode(201);
    }

    public function update(UpdateTagRequest $request, Tag $tag): TagResource
    {
        $this->authorize('update', $tag);
        return new TagResource($this->tagService->update($tag, $request->validated()));
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $this->authorize('delete', $tag);
        $this->tagService->delete($tag);
        return response()->json(null, 204);
    }
}
