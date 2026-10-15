<?php

namespace App\Http\Controllers\Api;

use App\Exports\CompanyReportExport;
use App\Exports\CustomerReportExport;
use App\Exports\LeadPipelineReportExport;
use App\Exports\ProductPerformanceReportExport;
use App\Exports\SalesReportExport;
use App\Exports\UserPerformanceExport;
use App\Exports\UserProductPerformanceExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\AsyncJob\AsyncJobResource;
use App\Services\AsyncJobService;
use App\Services\ReportExportService;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService,
        private AsyncJobService $asyncJobService,
        private ReportExportService $reportExportService,
    ) {}

    public function userPerformance(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $report = $this->reportService->userPerformance($request->query());

        return response()->json($report);
    }

    public function company(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $report = $this->reportService->companyReport($request->query());

        return response()->json($report);
    }

    public function customer(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $report = $this->reportService->customerReport($request->query());

        return response()->json($report);
    }

    public function leadPipeline(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $report = $this->reportService->leadPipeline($request->query());

        return response()->json($report);
    }

    public function productPerformance(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $report = $this->reportService->productPerformance($request->query());

        return response()->json($report);
    }

    public function userProductPerformance(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $report = $this->reportService->userProductPerformance($request->query());

        return response()->json($report);
    }

    public function sales(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $report = $this->reportService->salesReport($request->query());

        return response()->json($report);
    }

    public function exportUserPerformance(Request $request): BinaryFileResponse|Response
    {
        $this->authorize('view-reports');

        $request->validate(['format' => ['sometimes', 'string', Rule::in(['xlsx', 'pdf'])]]);

        $report = $this->reportService->userPerformance($request->except('format'));
        $format = $request->input('format', 'xlsx');

        if ($format === 'pdf') {
            return Pdf::loadView('exports.reports.user-performance', [
                'report' => $report,
                'locale' => App::getLocale(),
                'isRtl' => App::getLocale() === 'ar',
            ])->download('user-performance-report.pdf');
        }

        return Excel::download(new UserPerformanceExport($report), 'user-performance-report.xlsx');
    }

    public function exportCompany(Request $request): BinaryFileResponse|Response
    {
        $this->authorize('view-reports');

        $request->validate(['format' => ['sometimes', 'string', Rule::in(['xlsx', 'pdf'])]]);

        $report = $this->reportService->companyReport($request->except('format'));
        $format = $request->input('format', 'xlsx');

        if ($format === 'pdf') {
            return Pdf::loadView('exports.reports.company', [
                'report' => $report,
                'locale' => App::getLocale(),
                'isRtl' => App::getLocale() === 'ar',
            ])->download('company-report.pdf');
        }

        return Excel::download(new CompanyReportExport($report), 'company-report.xlsx');
    }

    public function exportCustomer(Request $request): BinaryFileResponse|Response
    {
        $this->authorize('view-reports');

        $request->validate(['format' => ['sometimes', 'string', Rule::in(['xlsx', 'pdf'])]]);

        $report = $this->reportService->customerReport($request->except('format'));
        $format = $request->input('format', 'xlsx');

        if ($format === 'pdf') {
            return Pdf::loadView('exports.reports.customer', [
                'report' => $report,
                'locale' => App::getLocale(),
                'isRtl' => App::getLocale() === 'ar',
            ])->download('customer-report.pdf');
        }

        return Excel::download(new CustomerReportExport($report), 'customer-report.xlsx');
    }

    public function exportLeadPipeline(Request $request): BinaryFileResponse|Response
    {
        $this->authorize('view-reports');

        $request->validate(['format' => ['sometimes', 'string', Rule::in(['xlsx', 'pdf'])]]);

        $report = $this->reportService->leadPipeline($request->except('format'));
        $format = $request->input('format', 'xlsx');

        if ($format === 'pdf') {
            return Pdf::loadView('exports.reports.lead-pipeline', [
                'report' => $report,
                'locale' => App::getLocale(),
                'isRtl' => App::getLocale() === 'ar',
            ])->download('lead-pipeline-report.pdf');
        }

        return Excel::download(new LeadPipelineReportExport($report), 'lead-pipeline-report.xlsx');
    }

    public function exportProductPerformance(Request $request): BinaryFileResponse|Response
    {
        $this->authorize('view-reports');

        $request->validate(['format' => ['sometimes', 'string', Rule::in(['xlsx', 'pdf'])]]);

        $report = $this->reportService->productPerformance($request->except('format'));
        $format = $request->input('format', 'xlsx');

        if ($format === 'pdf') {
            return Pdf::loadView('exports.reports.product-performance', [
                'report' => $report,
                'locale' => App::getLocale(),
                'isRtl' => App::getLocale() === 'ar',
            ])->download('product-performance-report.pdf');
        }

        return Excel::download(new ProductPerformanceReportExport($report), 'product-performance-report.xlsx');
    }

    public function exportUserProductPerformance(Request $request): BinaryFileResponse|Response
    {
        $this->authorize('view-reports');

        $request->validate(['format' => ['sometimes', 'string', Rule::in(['xlsx', 'pdf'])]]);

        $report = $this->reportService->userProductPerformance($request->except('format'));
        $format = $request->input('format', 'xlsx');

        if ($format === 'pdf') {
            return Pdf::loadView('exports.reports.user-product-performance', [
                'report' => $report,
                'locale' => App::getLocale(),
                'isRtl' => App::getLocale() === 'ar',
            ])->download('user-product-performance-report.pdf');
        }

        return Excel::download(new UserProductPerformanceExport($report), 'user-product-performance-report.xlsx');
    }

    public function exportSales(Request $request): BinaryFileResponse|Response
    {
        $this->authorize('view-reports');

        $request->validate(['format' => ['sometimes', 'string', Rule::in(['xlsx', 'pdf'])]]);

        $report = $this->reportService->salesReport($request->except('format'));
        $format = $request->input('format', 'xlsx');

        if ($format === 'pdf') {
            return Pdf::loadView('exports.reports.sales', [
                'report' => $report,
                'locale' => App::getLocale(),
                'isRtl' => App::getLocale() === 'ar',
            ])->download('sales-report.pdf');
        }

        return Excel::download(new SalesReportExport($report), 'sales-report.xlsx');
    }

    public function userPerformanceAsync(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $asyncJob = $this->asyncJobService->dispatch(
            ReportService::class,
            'userPerformance',
            [$request->all()],
            $request->user(),
        );

        return (new AsyncJobResource($asyncJob))
            ->response()
            ->setStatusCode(201);
    }

    public function companyAsync(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $asyncJob = $this->asyncJobService->dispatch(
            ReportService::class,
            'companyReport',
            [$request->all()],
            $request->user(),
        );

        return (new AsyncJobResource($asyncJob))
            ->response()
            ->setStatusCode(201);
    }

    public function customerAsync(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $asyncJob = $this->asyncJobService->dispatch(
            ReportService::class,
            'customerReport',
            [$request->all()],
            $request->user(),
        );

        return (new AsyncJobResource($asyncJob))
            ->response()
            ->setStatusCode(201);
    }

    public function leadPipelineAsync(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $asyncJob = $this->asyncJobService->dispatch(
            ReportService::class,
            'leadPipeline',
            [$request->all()],
            $request->user(),
        );

        return (new AsyncJobResource($asyncJob))
            ->response()
            ->setStatusCode(201);
    }

    public function productPerformanceAsync(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $asyncJob = $this->asyncJobService->dispatch(
            ReportService::class,
            'productPerformance',
            [$request->all()],
            $request->user(),
        );

        return (new AsyncJobResource($asyncJob))
            ->response()
            ->setStatusCode(201);
    }

    public function userProductPerformanceAsync(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $asyncJob = $this->asyncJobService->dispatch(
            ReportService::class,
            'userProductPerformance',
            [$request->all()],
            $request->user(),
        );

        return (new AsyncJobResource($asyncJob))
            ->response()
            ->setStatusCode(201);
    }

    public function salesAsync(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $asyncJob = $this->asyncJobService->dispatch(
            ReportService::class,
            'salesReport',
            [$request->all()],
            $request->user(),
        );

        return (new AsyncJobResource($asyncJob))
            ->response()
            ->setStatusCode(201);
    }

    public function exportUserPerformanceAsync(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $request->validate(['format' => ['sometimes', 'string', Rule::in(['xlsx', 'pdf'])]]);

        $asyncJob = $this->asyncJobService->dispatch(
            ReportExportService::class,
            'exportUserPerformance',
            [$request->except('format'), $request->input('format', 'xlsx'), App::getLocale()],
            $request->user(),
        );

        return (new AsyncJobResource($asyncJob))
            ->response()
            ->setStatusCode(201);
    }

    public function exportCompanyAsync(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $request->validate(['format' => ['sometimes', 'string', Rule::in(['xlsx', 'pdf'])]]);

        $asyncJob = $this->asyncJobService->dispatch(
            ReportExportService::class,
            'exportCompany',
            [$request->except('format'), $request->input('format', 'xlsx'), App::getLocale()],
            $request->user(),
        );

        return (new AsyncJobResource($asyncJob))
            ->response()
            ->setStatusCode(201);
    }

    public function exportCustomerAsync(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $request->validate(['format' => ['sometimes', 'string', Rule::in(['xlsx', 'pdf'])]]);

        $asyncJob = $this->asyncJobService->dispatch(
            ReportExportService::class,
            'exportCustomer',
            [$request->except('format'), $request->input('format', 'xlsx'), App::getLocale()],
            $request->user(),
        );

        return (new AsyncJobResource($asyncJob))
            ->response()
            ->setStatusCode(201);
    }

    public function exportLeadPipelineAsync(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $request->validate(['format' => ['sometimes', 'string', Rule::in(['xlsx', 'pdf'])]]);

        $asyncJob = $this->asyncJobService->dispatch(
            ReportExportService::class,
            'exportLeadPipeline',
            [$request->except('format'), $request->input('format', 'xlsx'), App::getLocale()],
            $request->user(),
        );

        return (new AsyncJobResource($asyncJob))->response()->setStatusCode(201);
    }

    public function exportProductPerformanceAsync(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $request->validate(['format' => ['sometimes', 'string', Rule::in(['xlsx', 'pdf'])]]);

        $asyncJob = $this->asyncJobService->dispatch(
            ReportExportService::class,
            'exportProductPerformance',
            [$request->except('format'), $request->input('format', 'xlsx'), App::getLocale()],
            $request->user(),
        );

        return (new AsyncJobResource($asyncJob))->response()->setStatusCode(201);
    }

    public function exportUserProductPerformanceAsync(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $request->validate(['format' => ['sometimes', 'string', Rule::in(['xlsx', 'pdf'])]]);

        $asyncJob = $this->asyncJobService->dispatch(
            ReportExportService::class,
            'exportUserProductPerformance',
            [$request->except('format'), $request->input('format', 'xlsx'), App::getLocale()],
            $request->user(),
        );

        return (new AsyncJobResource($asyncJob))->response()->setStatusCode(201);
    }

    public function exportSalesAsync(Request $request): JsonResponse
    {
        $this->authorize('view-reports');

        $request->validate(['format' => ['sometimes', 'string', Rule::in(['xlsx', 'pdf'])]]);

        $asyncJob = $this->asyncJobService->dispatch(
            ReportExportService::class,
            'exportSales',
            [$request->except('format'), $request->input('format', 'xlsx'), App::getLocale()],
            $request->user(),
        );

        return (new AsyncJobResource($asyncJob))->response()->setStatusCode(201);
    }
}
