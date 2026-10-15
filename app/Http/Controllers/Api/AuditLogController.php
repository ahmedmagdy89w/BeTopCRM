<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLog\AuditLogResource;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', \App\Models\AuditLog::class);
        $logs = $this->auditLogService->list($request->all());
        return AuditLogResource::collection($logs);
    }

    public function forEntity(Request $request, string $type, int $id): AnonymousResourceCollection
    {
        $this->authorize('viewAny', \App\Models\AuditLog::class);
        $logs = $this->auditLogService->forEntity($type, $id, $request->all());
        return AuditLogResource::collection($logs);
    }
}
