<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    public function leadStats(): JsonResponse
    {
        $stats = $this->dashboardService->leadStats();
        return response()->json(['data' => $stats]);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $this->dashboardService->index($request->only(['date_from', 'date_to']), $request->user());
        return response()->json(['data' => $data]);
    }

    public function activityFeed(Request $request): JsonResponse
    {
        $perPage = min((int) ($request->per_page ?? 20), 50);
        $feed    = $this->dashboardService->activityFeed($request->user(), $perPage);
        return response()->json($feed);
    }
}

