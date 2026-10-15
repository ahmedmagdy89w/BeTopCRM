<?php

namespace App\Exports;

use App\Services\CompanySettingsService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class UserProductPerformanceExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private array $report) {}

    public function array(): array
    {
        $rows = [];

        foreach ($this->report['data'] as $row) {
            if ($row['booked_value_by_currency'] === []) {
                $rows[] = [
                    $row['user']['name'],
                    $row['product']['name'],
                    $row['product']['sku'] ?? '-',
                    '',
                    $row['opportunities_count'],
                    $row['won_count'],
                    $row['open_count'],
                    $row['quantity'],
                    '0.00',
                ];

                continue;
            }

            foreach ($row['booked_value_by_currency'] as $sale) {
                $rows[] = [
                    $row['user']['name'],
                    $row['product']['name'],
                    $row['product']['sku'] ?? '-',
                    $sale['currency'],
                    $row['opportunities_count'],
                    $row['won_count'],
                    $row['open_count'],
                    $row['quantity'],
                    $sale['value'],
                ];
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        $labels = CompanySettingsService::productLabels(app()->getLocale(), __('reports.product'), __('reports.products'));

        return [
            __('reports.user'),
            $labels['singular'],
            __('reports.sku'),
            __('reports.currency'),
            __('reports.total_opportunities'),
            __('reports.won_opportunities_count'),
            __('reports.open_opportunities'),
            __('reports.quantity'),
            __('reports.booked_value'),
        ];
    }

    public function title(): string
    {
        return __('reports.user_product_performance_title');
    }
}
