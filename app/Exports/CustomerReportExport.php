<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class CustomerReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private array $report) {}

    public function array(): array
    {
        $rows = array_map(fn ($row) => [
            $row['customer']['company_name'],
            $row['customer']['contact_name'],
            $row['customer']['status'],
            $row['customer']['health_score'],
            $row['account_manager']['name'],
            collect($row['total_won_opportunity_value_by_currency'])->map(fn ($item) => $item['value'].' '.$item['currency'])->join(', '),
            $row['won_opportunities_count'],
            $row['activities_count'],
            collect($row['activities_by_type'])
                ->map(fn ($count, $type) => "{$type}: {$count}")
                ->join(', '),
            $row['last_contacted_at'] ?? '-',
            $row['days_since_last_contact'] ?? '-',
            $row['next_follow_up_at'] ?? '-',
            $row['days_as_customer'] ?? '-',
            $row['converted_at'] ?? '-',
        ], $this->report['data']);

        $summary = $this->report['summary'];

        $rows[] = [];
        $rows[] = [__('reports.summary')];
        $rows[] = [__('reports.total_customers'), $summary['total_customers']];
        $rows[] = [__('reports.total_won_opportunity_revenue'), collect($summary['total_won_opportunity_revenue_by_currency'])->map(fn ($item) => $item['value'].' '.$item['currency'])->join(', ')];
        $rows[] = [__('reports.avg_health_score'), $summary['avg_health_score']];
        $rows[] = [__('reports.churn_rate'), $summary['churn_rate'].'%'];
        $rows[] = [__('reports.retention_rate'), $summary['retention_rate'].'%'];

        return $rows;
    }

    public function headings(): array
    {
        return [
            __('reports.company_name'),
            __('reports.contact_name'),
            __('reports.status'),
            __('reports.health_score'),
            __('reports.account_manager'),
            __('reports.total_won_opportunity_value'),
            __('reports.won_opportunities_count'),
            __('reports.activities_count'),
            __('reports.activities_by_type'),
            __('reports.last_contacted_at'),
            __('reports.days_since_last_contact'),
            __('reports.next_follow_up_at'),
            __('reports.days_as_customer'),
            __('reports.converted_at'),
        ];
    }

    public function title(): string
    {
        return __('reports.customer_title');
    }
}
