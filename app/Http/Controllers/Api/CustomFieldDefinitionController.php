<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomField\StoreCustomFieldDefinitionRequest;
use App\Http\Requests\CustomField\UpdateCustomFieldDefinitionRequest;
use App\Http\Resources\CustomField\CustomFieldDefinitionResource;
use App\Models\CustomFieldDefinition;
use App\Models\ProductCategory;
use App\Services\CustomFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CustomFieldDefinitionController extends Controller
{
    public function __construct(private CustomFieldService $customFieldService) {}

    public function index(Request $request, string $entityType): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CustomFieldDefinition::class);
        $definitions = $this->customFieldService->listDefinitions($entityType);

        return CustomFieldDefinitionResource::collection($definitions);
    }

    public function store(StoreCustomFieldDefinitionRequest $request, string $entityType): JsonResponse
    {
        $this->authorize('create', CustomFieldDefinition::class);

        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $def = $this->customFieldService->createDefinition($entityType, $data);

        return (new CustomFieldDefinitionResource($def->load('group')))->response()->setStatusCode(201);
    }

    public function storeCategory(StoreCustomFieldDefinitionRequest $request, string $entityType, ProductCategory $category): JsonResponse
    {
        $this->authorize('create', CustomFieldDefinition::class);

        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $definition = $this->customFieldService->createCategoryDefinition($entityType, $category->id, $data);

        return (new CustomFieldDefinitionResource($definition->load('group')))->response()->setStatusCode(201);
    }

    public function show(CustomFieldDefinition $definition): CustomFieldDefinitionResource
    {
        $this->authorize('view', $definition);

        return new CustomFieldDefinitionResource($definition->load('group'));
    }

    public function update(UpdateCustomFieldDefinitionRequest $request, CustomFieldDefinition $definition): CustomFieldDefinitionResource
    {
        $this->authorize('update', $definition);

        $def = $this->customFieldService->updateDefinition($definition, $request->validated());

        return new CustomFieldDefinitionResource($def->load('group'));
    }

    public function destroy(CustomFieldDefinition $definition): Response
    {
        $this->authorize('delete', $definition);
        $this->customFieldService->deleteDefinition($definition);

        return response()->noContent();
    }
}
