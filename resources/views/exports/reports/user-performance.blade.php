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
            background-color: #3b82f6;
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
        .footer { margin-top: 24px; font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>

<h1>{{ __('reports.user_performance_title') }}</h1>
<div class="meta">{{ __('reports.generated_at') }}: {{ now()->format('Y-m-d H:i') }}</div>

<table>
    <thead>
        <tr>
            <th>{{ __('reports.user') }}</th>
            <th>{{ __('reports.leads_assigned') }}</th>
            <th>{{ __('reports.opportunities_assigned') }}</th>
            <th>{{ __('reports.opportunities_won') }}</th>
            <th>{{ __('reports.opportunities_lost') }}</th>
            <th>{{ __('reports.opportunities_in_progress') }}</th>
            <th>{{ __('reports.opportunity_win_rate') }}</th>
            <th>{{ __('reports.total_won_value') }}</th>
            <th>{{ __('reports.avg_opportunity_value') }}</th>
            <th>{{ __('reports.activities_logged') }}</th>
            <th>{{ __('reports.activities_by_type') }}</th>
            <th>{{ __('reports.avg_days_to_close') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($report['data'] as $row)
        <tr>
            <td>{{ $row['user']['name'] }}</td>
            <td>{{ $row['leads_assigned'] }}</td>
            <td>{{ $row['opportunities_assigned'] }}</td>
            <td>{{ $row['opportunities_won'] }}</td>
            <td>{{ $row['opportunities_lost'] }}</td>
            <td>{{ $row['opportunities_in_progress'] }}</td>
            <td>{{ $row['opportunity_win_rate'] }}%</td>
            <td>{{ collect($row['total_won_value_by_currency'])->map(fn ($item) => $item['value'].' '.$item['currency'])->join(', ') }}</td>
            <td>{{ collect($row['total_won_value_by_currency'])->map(fn ($item) => $item['avg_opportunity_value'].' '.$item['currency'])->join(', ') }}</td>
            <td>{{ $row['activities_logged'] }}</td>
            <td>{{ collect($row['activities_by_type'])->map(fn ($count, $type) => "{$type}: {$count}")->join(', ') }}</td>
            <td>{{ $row['avg_days_to_close'] ?? '-' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="section-title">{{ __('reports.summary') }}</div>
<table class="summary-table">
    <tr><td>{{ __('reports.total_users') }}</td><td>{{ $report['summary']['total_users'] }}</td></tr>
    <tr><td>{{ __('reports.total_leads_assigned') }}</td><td>{{ $report['summary']['total_leads_assigned'] }}</td></tr>
    <tr><td>{{ __('reports.total_opportunities_assigned') }}</td><td>{{ $report['summary']['total_opportunities_assigned'] }}</td></tr>
    <tr><td>{{ __('reports.total_won_value') }}</td><td>{{ collect($report['summary']['total_won_value_by_currency'])->map(fn ($item) => $item['value'].' '.$item['currency'])->join(', ') }}</td></tr>
    <tr><td>{{ __('reports.overall_opportunity_win_rate') }}</td><td>{{ $report['summary']['overall_opportunity_win_rate'] }}%</td></tr>
</table>

<div class="footer">{{ config('app.name') }}</div>

</body>
</html>
