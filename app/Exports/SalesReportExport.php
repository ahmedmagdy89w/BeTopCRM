<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class SalesReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private array $report) {}

    public function array(): array
    {
        $rows = [];

        foreach ($this->report['data'] as $row) {
            if ($row['revenue_by_currency'] === []) {
                $rows[] = [
                    $row['period'],
                    '',
                    '0.00',
                    $row['won_count'],
                    $row['open_count'],
                    $row['open_value'],
                ];

                continue;
            }

            foreach ($row['revenue_by_currency'] as $sale) {
                $rows[] = [
                    $row['period'],
                    $sale['currency'],
                    $sale['value'],
                    $row['won_count'],
                    $row['open_count'],
                    $row['open_value'],
                ];
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            __('reports.period'),
            __('reports.currency'),
            __('reports.revenue'),
            __('reports.won_opportunities_count'),
            __('reports.open_opportunities'),
            __('reports.open_value'),
        ];
    }

    public function title(): string
    {
        return __('reports.sales_title');
    }
}
