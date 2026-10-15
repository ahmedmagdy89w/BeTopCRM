<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\GlobalSearchRequest;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __construct(private SearchService $searchService) {}

    public function index(GlobalSearchRequest $request): JsonResponse
    {
        $results = $this->searchService->search(
            $request->q,
            $request->getTypes(),
            $request->getLimit(),
            $request->user()
        );

        return response()->json(['data' => $results['results']]);
    }
}
