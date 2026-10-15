<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class UserPerformanceExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private array $report) {}

    public function array(): array
    {
        $rows = array_map(fn ($row) => [
            $row['user']['name'],
            $row['leads_assigned'],
            $row['opportunities_assigned'],
            $row['opportunities_won'],
            $row['opportunities_lost'],
            $row['opportunities_in_progress'],
            $row['opportunity_win_rate'].'%',
            collect($row['total_won_value_by_currency'])->map(fn ($item) => $item['value'].' '.$item['currency'])->join(', '),
            collect($row['total_won_value_by_currency'])->map(fn ($item) => $item['avg_opportunity_value'].' '.$item['currency'])->join(', '),
            $row['activities_logged'],
            collect($row['activities_by_type'])
                ->map(fn ($count, $type) => "{$type}: {$count}")
                ->join(', '),
            $row['avg_days_to_close'] ?? '-',
        ], $this->report['data']);

        $summary = $this->report['summary'];

        $rows[] = [];
        $rows[] = [__('reports.summary')];
        $rows[] = [__('reports.total_users'), $summary['total_users']];
        $rows[] = [__('reports.total_leads_assigned'), $summary['total_leads_assigned']];
        $rows[] = [__('reports.total_opportunities_assigned'), $summary['total_opportunities_assigned']];
        $rows[] = [__('reports.total_won_value'), collect($summary['total_won_value_by_currency'])->map(fn ($item) => $item['value'].' '.$item['currency'])->join(', ')];
        $rows[] = [__('reports.overall_opportunity_win_rate'), $summary['overall_opportunity_win_rate'].'%'];

        return $rows;
    }

    public function headings(): array
    {
        return [
            __('reports.user'),
            __('reports.leads_assigned'),
            __('reports.opportunities_assigned'),
            __('reports.opportunities_won'),
            __('reports.opportunities_lost'),
            __('reports.opportunities_in_progress'),
            __('reports.opportunity_win_rate'),
            __('reports.total_won_value'),
            __('reports.avg_opportunity_value'),
            __('reports.activities_logged'),
            __('reports.activities_by_type'),
            __('reports.avg_days_to_close'),
        ];
    }

    public function title(): string
    {
        return __('reports.user_performance_title');
    }
}
