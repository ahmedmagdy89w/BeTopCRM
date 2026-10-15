<?php

namespace App\Exports;

use App\Models\Opportunity;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class CompanyReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private array $report) {}

    public function array(): array
    {
        $rows = array_map(fn ($row) => [
            $row['organization']['name'],
            $row['organization']['industry'] ?? '-',
            $row['total_leads'],
            ...array_map(fn ($stage) => $row['leads_by_status'][$stage] ?? 0, Opportunity::STAGES),
            $row['conversion_rate'].'%',
            collect($row['total_estimated_value_by_currency'])->map(fn ($item) => $item['value'].' '.$item['currency'])->join(', '),
            collect($row['total_won_value_by_currency'])->map(fn ($item) => $item['value'].' '.$item['currency'])->join(', '),
            $row['contacts_count'],
            $row['latest_activity_date'] ?? '-',
        ], $this->report['data']);

        $summary = $this->report['summary'];

        $rows[] = [];
        $rows[] = [__('reports.summary')];
        $rows[] = [__('reports.total_organizations'), $summary['total_organizations']];
        $rows[] = [__('reports.total_leads_across_orgs'), $summary['total_leads_across_orgs']];
        $rows[] = [__('reports.total_won_value'), collect($summary['total_won_value_by_currency'])->map(fn ($item) => $item['value'].' '.$item['currency'])->join(', ')];

        if (! empty($summary['top_industries'])) {
            $rows[] = [];
            $rows[] = [__('reports.top_industries')];
            $rows[] = [__('reports.industry'), __('reports.won_value'), __('reports.lead_count')];
            foreach ($summary['top_industries'] as $industry) {
                $rows[] = [$industry['industry'], collect($industry['won_value_by_currency'])->map(fn ($item) => $item['value'].' '.$item['currency'])->join(', '), $industry['lead_count']];
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            __('reports.organization'),
            __('reports.industry'),
            __('reports.total_leads'),
            ...array_map(fn ($stage) => __('reports.status_'.$stage), Opportunity::STAGES),
            __('reports.conversion_rate'),
            __('reports.total_estimated_value'),
            __('reports.total_won_value'),
            __('reports.contacts_count'),
            __('reports.latest_activity_date'),
        ];
    }

    public function title(): string
    {
        return __('reports.company_title');
    }
}
