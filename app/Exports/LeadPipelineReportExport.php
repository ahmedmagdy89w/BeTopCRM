<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class LeadPipelineReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private array $report) {}

    public function array(): array
    {
        $rows = [];

        foreach ($this->report['data']['by_status'] as $status => $count) {
            $rows[] = [__('reports.status_'.$status), '', $count, ''];
        }
        foreach ($this->report['data']['by_source'] as $source) {
            $rows[] = [__('reports.source'), $source['source'] ?? __('reports.unknown_source'), $source['lead_count'], $source['avg_score'] ?? '-'];
        }
        foreach ($this->report['data']['by_owner'] as $owner) {
            $rows[] = [__('reports.owner'), $owner['owner']['name'] ?? __('reports.unassigned'), $owner['lead_count'], $owner['avg_score'] ?? '-'];
        }
        foreach ($this->report['data']['top_scored_leads'] as $lead) {
            $rows[] = [__('reports.top_scored_lead'), $lead['customer']['name'], $lead['score'], $lead['source'] ?? '-'];
        }

        return $rows;
    }

    public function headings(): array
    {
        return [__('reports.section'), __('reports.name'), __('reports.total_leads'), __('reports.avg_health_score')];
    }

    public function title(): string
    {
        return __('reports.lead_pipeline_title');
    }
}
