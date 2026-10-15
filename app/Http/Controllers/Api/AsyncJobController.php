<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AsyncJob\AsyncJobResource;
use App\Models\AsyncJob;
use App\Services\AsyncJobService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AsyncJobController extends Controller
{
    public function __construct(private AsyncJobService $asyncJobService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AsyncJob::class);

        $jobs = $this->asyncJobService->listForUser(
            $request->user(),
            $request->query()
        );

        return AsyncJobResource::collection($jobs);
    }

    public function show(AsyncJob $asyncJob): AsyncJobResource
    {
        $this->authorize('view', $asyncJob);

        return new AsyncJobResource($asyncJob);
    }

    public function download(AsyncJob $asyncJob): StreamedResponse
    {
        $this->authorize('view', $asyncJob);

        abort_unless($asyncJob->isCompleted(), 404, __('messages.async_job_not_ready'));

        $filePath = $asyncJob->result['file_path'] ?? null;

        abort_unless($filePath && Storage::disk('local')->exists($filePath), 404, __('messages.file_not_found'));

        return Storage::disk('local')->download(
            $filePath,
            $asyncJob->result['file_name'] ?? basename($filePath),
            ['Content-Type' => $asyncJob->result['mime_type'] ?? 'application/octet-stream']
        );
    }
}
