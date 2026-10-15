<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Import\StoreImportRequest;
use App\Http\Resources\Import\ImportResource;
use App\Models\Import;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function __construct(private ImportService $importService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('import-data');

        $imports = $this->importService->list($request->user());

        return response()->json(['data' => ImportResource::collection($imports)]);
    }

    public function store(StoreImportRequest $request, string $entityType): JsonResponse
    {
        $this->authorize('import-data');

        abort_unless(in_array($entityType, Import::ENTITY_TYPES), 422, 'Invalid entity type.');

        $import = $this->importService->start($entityType, $request->file('file'), $request->user());

        return (new ImportResource($import))->response()->setStatusCode(201);
    }

    public function show(Import $import): ImportResource
    {
        $this->authorize('view', $import);

        return new ImportResource($this->importService->show($import));
    }

    public function template(string $entityType): StreamedResponse
    {
        $this->authorize('import-data');

        abort_unless(in_array($entityType, Import::ENTITY_TYPES), 422, 'Invalid entity type.');

        return $this->importService->template($entityType);
    }
}
