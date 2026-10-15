<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(private ExportService $exportService) {}

    public function leads(Request $request): StreamedResponse
    {
        $this->authorize('export-data');

        return $this->exportService->leads($request->all(), $request->user());
    }

    public function opportunities(Request $request): StreamedResponse
    {
        $this->authorize('export-data');

        return $this->exportService->opportunities($request->all(), $request->user());
    }

    public function customers(Request $request): StreamedResponse
    {
        $this->authorize('export-data');

        return $this->exportService->customers($request->all(), $request->user());
    }

    public function products(Request $request): StreamedResponse
    {
        $this->authorize('export-data');

        return $this->exportService->products($request->all(), $request->user());
    }

    public function categories(Request $request): StreamedResponse
    {
        $this->authorize('export-data');

        return $this->exportService->categories($request->all(), $request->user());
    }
}
