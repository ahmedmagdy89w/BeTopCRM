<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\Task\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskController extends Controller
{
    public function __construct(private TaskService $taskService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Task::class);
        $tasks = $this->taskService->list($request->all(), $request->user());
        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('create', Task::class);
        $task = $this->taskService->create($request->validated(), $request->user());
        return (new TaskResource($task->load(['assignee:id,name', 'creator:id,name'])))->response()->setStatusCode(201);
    }

    public function show(Task $task): TaskResource
    {
        $this->authorize('view', $task);
        return new TaskResource($task->load(['assignee:id,name', 'creator:id,name']));
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);
        $task = $this->taskService->update($task, $request->validated());
        return new TaskResource($task);
    }

    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);
        $this->taskService->delete($task);
        return response()->json(null, 204);
    }

    public function complete(Task $task): TaskResource
    {
        $this->authorize('complete', $task);
        $task = $this->taskService->complete($task);
        return new TaskResource($task->load(['assignee:id,name', 'creator:id,name']));
    }

    public function mySummary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);
        $summary = $this->taskService->mySummary($request->user());
        return response()->json(['data' => $summary]);
    }
}
