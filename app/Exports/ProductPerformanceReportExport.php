<?php

namespace App\Exports;

use App\Services\CompanySettingsService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProductPerformanceReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private array $report) {}

    public function array(): array
    {
        $rows = [];

        foreach ($this->report['data'] as $product) {
            if ($product['booked_sales'] === []) {
                $rows[] = [
                    $product['product']['name'],
                    $product['product']['category']['name'] ?? '-',
                    '',
                    0,
                    0,
                    '0.00',
                    $product['open_pipeline']['opportunities_count'],
                    $product['open_pipeline']['quantity'],
                ];

                continue;
            }

            foreach ($product['booked_sales'] as $sale) {
                $rows[] = [
                    $product['product']['name'],
                    $product['product']['category']['name'] ?? '-',
                    $sale['currency'],
                    $sale['opportunities_count'],
                    $sale['quantity'],
                    $sale['booked_value'],
                    $product['open_pipeline']['opportunities_count'],
                    $product['open_pipeline']['quantity'],
                ];
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        $labels = CompanySettingsService::productLabels(app()->getLocale(), __('reports.product'), __('reports.products'));

        return [
            $labels['singular'],
            __('reports.category'),
            __('reports.currency'),
            __('reports.won_opportunities_count'),
            __('reports.quantity'),
            __('reports.booked_value'),
            __('reports.open_opportunities'),
            __('reports.pipeline_quantity'),
        ];
    }

    public function title(): string
    {
        return CompanySettingsService::productReportTitle(app()->getLocale());
    }
}
