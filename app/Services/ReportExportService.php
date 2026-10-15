<?php

namespace App\Services;

use App\Exports\CompanyReportExport;
use App\Exports\CustomerReportExport;
use App\Exports\LeadPipelineReportExport;
use App\Exports\ProductPerformanceReportExport;
use App\Exports\SalesReportExport;
use App\Exports\UserPerformanceExport;
use App\Exports\UserProductPerformanceExport;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ReportExportService
{
    public function __construct(private ReportService $reportService) {}

    public function exportUserPerformance(array $filters, string $format = 'xlsx', string $locale = 'en'): array
    {
        App::setLocale($locale);

        $report = $this->reportService->userPerformance($filters);
        $filename = 'user-performance-report-'.Str::random(8);

        if ($format === 'pdf') {
            return $this->storePdf('exports.reports.user-performance', $report, $filename, $locale);
        }

        return $this->storeExcel(new UserPerformanceExport($report), $filename);
    }

    public function exportCompany(array $filters, string $format = 'xlsx', string $locale = 'en'): array
    {
        App::setLocale($locale);

        $report = $this->reportService->companyReport($filters);
        $filename = 'company-report-'.Str::random(8);

        if ($format === 'pdf') {
            return $this->storePdf('exports.reports.company', $report, $filename, $locale);
        }

        return $this->storeExcel(new CompanyReportExport($report), $filename);
    }

    public function exportCustomer(array $filters, string $format = 'xlsx', string $locale = 'en'): array
    {
        App::setLocale($locale);

        $report = $this->reportService->customerReport($filters);
        $filename = 'customer-report-'.Str::random(8);

        if ($format === 'pdf') {
            return $this->storePdf('exports.reports.customer', $report, $filename, $locale);
        }

        return $this->storeExcel(new CustomerReportExport($report), $filename);
    }

    public function exportLeadPipeline(array $filters, string $format = 'xlsx', string $locale = 'en'): array
    {
        App::setLocale($locale);

        $report = $this->reportService->leadPipeline($filters);
        $filename = 'lead-pipeline-report-'.Str::random(8);

        if ($format === 'pdf') {
            return $this->storePdf('exports.reports.lead-pipeline', $report, $filename, $locale);
        }

        return $this->storeExcel(new LeadPipelineReportExport($report), $filename);
    }

    public function exportProductPerformance(array $filters, string $format = 'xlsx', string $locale = 'en'): array
    {
        App::setLocale($locale);

        $report = $this->reportService->productPerformance($filters);
        $filename = 'product-performance-report-'.Str::random(8);

        if ($format === 'pdf') {
            return $this->storePdf('exports.reports.product-performance', $report, $filename, $locale);
        }

        return $this->storeExcel(new ProductPerformanceReportExport($report), $filename);
    }

    public function exportUserProductPerformance(array $filters, string $format = 'xlsx', string $locale = 'en'): array
    {
        App::setLocale($locale);

        $report = $this->reportService->userProductPerformance($filters);
        $filename = 'user-product-performance-report-'.Str::random(8);

        if ($format === 'pdf') {
            return $this->storePdf('exports.reports.user-product-performance', $report, $filename, $locale);
        }

        return $this->storeExcel(new UserProductPerformanceExport($report), $filename);
    }

    public function exportSales(array $filters, string $format = 'xlsx', string $locale = 'en'): array
    {
        App::setLocale($locale);

        $report = $this->reportService->salesReport($filters);
        $filename = 'sales-report-'.Str::random(8);

        if ($format === 'pdf') {
            return $this->storePdf('exports.reports.sales', $report, $filename, $locale);
        }

        return $this->storeExcel(new SalesReportExport($report), $filename);
    }

    private function storeExcel(object $export, string $filename): array
    {
        $path = "exports/{$filename}.xlsx";

        Excel::store($export, $path, 'local');

        return [
            'file_path' => $path,
            'file_name' => basename($path),
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    private function storePdf(string $view, array $report, string $filename, string $locale): array
    {
        $path = "exports/{$filename}.pdf";

        $pdf = ArabicPdfService::loadView($view, [
            'report' => $report,
            'locale' => $locale,
            'isRtl' => $locale === 'ar',
        ]);

        Storage::disk('local')->put($path, $pdf->output());

        return [
            'file_path' => $path,
            'file_name' => basename($path),
            'mime_type' => 'application/pdf',
        ];
    }
}
