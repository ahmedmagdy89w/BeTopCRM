<!DOCTYPE html>
<html dir="{{ $isRtl ? 'rtl' : 'ltr' }}" lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            margin: 20px;
            color: #333;
            direction: {{ $isRtl ? 'rtl' : 'ltr' }};
        }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { font-size: 10px; color: #888; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th {
            background-color: #10b981;
            color: #fff;
            padding: 7px 8px;
            text-align: {{ $isRtl ? 'right' : 'left' }};
            font-size: 10px;
        }
        td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
        tr:nth-child(even) td { background-color: #f9fafb; }
        .section-title { font-size: 13px; font-weight: bold; margin: 16px 0 8px; }
        .summary-table { width: 50%; }
        .summary-table td:first-child { color: #6b7280; }
        .summary-table td:last-child { font-weight: bold; }
        .industries-table th { background-color: #6b7280; }
        .footer { margin-top: 24px; font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>

<h1>{{ __('reports.company_title') }}</h1>
<div class="meta">{{ __('reports.generated_at') }}: {{ now()->format('Y-m-d H:i') }}</div>

<table>
    <thead>
        <tr>
            <th>{{ __('reports.organization') }}</th>
            <th>{{ __('reports.industry') }}</th>
            <th>{{ __('reports.total_leads') }}</th>
            @foreach(\App\Models\Opportunity::STAGES as $stage)
            <th>{{ __('reports.status_'.$stage) }}</th>
            @endforeach
            <th>{{ __('reports.conversion_rate') }}</th>
            <th>{{ __('reports.total_estimated_value') }}</th>
            <th>{{ __('reports.total_won_value') }}</th>
            <th>{{ __('reports.contacts_count') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($report['data'] as $row)
        <tr>
            <td>{{ $row['organization']['name'] }}</td>
            <td>{{ $row['organization']['industry'] ?? '-' }}</td>
            <td>{{ $row['total_leads'] }}</td>
            @foreach(\App\Models\Opportunity::STAGES as $stage)
            <td>{{ $row['leads_by_status'][$stage] ?? 0 }}</td>
            @endforeach
            <td>{{ $row['conversion_rate'] }}%</td>
            <td>{{ collect($row['total_estimated_value_by_currency'])->map(fn ($item) => $item['value'].' '.$item['currency'])->join(', ') }}</td>
            <td>{{ collect($row['total_won_value_by_currency'])->map(fn ($item) => $item['value'].' '.$item['currency'])->join(', ') }}</td>
            <td>{{ $row['contacts_count'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="section-title">{{ __('reports.summary') }}</div>
<table class="summary-table">
    <tr><td>{{ __('reports.total_organizations') }}</td><td>{{ $report['summary']['total_organizations'] }}</td></tr>
    <tr><td>{{ __('reports.total_leads_across_orgs') }}</td><td>{{ $report['summary']['total_leads_across_orgs'] }}</td></tr>
    <tr><td>{{ __('reports.total_won_value') }}</td><td>{{ collect($report['summary']['total_won_value_by_currency'])->map(fn ($item) => $item['value'].' '.$item['currency'])->join(', ') }}</td></tr>
</table>

@if(!empty($report['summary']['top_industries']))
<div class="section-title">{{ __('reports.top_industries') }}</div>
<table class="industries-table">
    <thead>
        <tr>
            <th>{{ __('reports.industry') }}</th>
            <th>{{ __('reports.won_value') }}</th>
            <th>{{ __('reports.lead_count') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($report['summary']['top_industries'] as $industry)
        <tr>
            <td>{{ $industry['industry'] ?? '-' }}</td>
            <td>{{ collect($industry['won_value_by_currency'])->map(fn ($item) => $item['value'].' '.$item['currency'])->join(', ') }}</td>
            <td>{{ $industry['lead_count'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<div class="footer">{{ config('app.name') }}</div>

</body>
</html>
