<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomField\StoreCustomFieldGroupRequest;
use App\Http\Requests\CustomField\UpdateCustomFieldGroupRequest;
use App\Http\Resources\CustomField\CustomFieldEntityResource;
use App\Http\Resources\CustomField\CustomFieldGroupResource;
use App\Http\Resources\CustomField\CustomFieldGroupWithDefinitionsResource;
use App\Models\CustomFieldGroup;
use App\Models\ProductCategory;
use App\Services\CustomFieldEntityRegistry;
use App\Services\CustomFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class CustomFieldGroupController extends Controller
{
    public function __construct(
        private CustomFieldEntityRegistry $registry,
        private CustomFieldService $customFieldService,
    ) {}

    public function entities(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CustomFieldGroup::class);

        $entities = collect($this->registry->all())->map(
            fn (array $config, string $entityType): array => [
                'entity_type' => $entityType,
                'label' => $config['label'],
                'supports_categories' => isset($config['category']),
            ]
        )->values();

        return CustomFieldEntityResource::collection($entities);
    }

    public function showEntity(string $entityType): CustomFieldGroupWithDefinitionsResource
    {
        $this->authorize('viewAny', CustomFieldGroup::class);
        $this->registry->guard($entityType);

        $group = $this->customFieldService->findEntityGroup($entityType);
        $definitions = $group?->definitions ?? new Collection;

        return new CustomFieldGroupWithDefinitionsResource([
            'group' => $group,
            'definitions' => $definitions,
        ]);
    }

    public function showCategory(string $entityType, ProductCategory $category): CustomFieldGroupWithDefinitionsResource
    {
        $this->authorize('viewAny', CustomFieldGroup::class);
        $this->registry->guard($entityType, CustomFieldEntityRegistry::SCOPE_CATEGORY);

        $group = $this->customFieldService->findCategoryGroup($entityType, $category->id);
        $definitions = $group?->definitions ?? new Collection;

        return new CustomFieldGroupWithDefinitionsResource([
            'group' => $group,
            'definitions' => $definitions,
        ]);
    }

    public function store(StoreCustomFieldGroupRequest $request): JsonResponse
    {
        $this->authorize('create', CustomFieldGroup::class);

        $data = $request->validated();
        $categoryId = $data['scope'] === CustomFieldEntityRegistry::SCOPE_CATEGORY
            ? (int) $data['category_id']
            : null;

        $group = $categoryId === null
            ? $this->customFieldService->entityGroup($data['entity_type'], $request->user()->getKey())
            : $this->customFieldService->categoryGroup($data['entity_type'], $categoryId, $request->user()->getKey());

        $group = $this->customFieldService->updateGroup($group, [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return (new CustomFieldGroupResource($group))->response()->setStatusCode(201);
    }

    public function update(UpdateCustomFieldGroupRequest $request, CustomFieldGroup $group): CustomFieldGroupResource
    {
        $this->authorize('update', $group);

        $group = $this->customFieldService->updateGroup($group, $request->validated());

        return new CustomFieldGroupResource($group);
    }

    public function destroy(CustomFieldGroup $group): Response
    {
        $this->authorize('delete', $group);
        $this->customFieldService->deleteGroup($group);

        return response()->noContent();
    }
}
