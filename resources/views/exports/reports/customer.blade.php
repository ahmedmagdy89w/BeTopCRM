<!DOCTYPE html>
<html dir="{{ $isRtl ? 'rtl' : 'ltr' }}" lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: landscape; }
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
            background-color: #8b5cf6;
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

<h1>{{ __('reports.customer_title') }}</h1>
<div class="meta">{{ __('reports.generated_at') }}: {{ now()->format('Y-m-d H:i') }}</div>

<table>
    <thead>
        <tr>
            <th>{{ __('reports.company_name') }}</th>
            <th>{{ __('reports.contact_name') }}</th>
            <th>{{ __('reports.status') }}</th>
            <th>{{ __('reports.health_score') }}</th>
            <th>{{ __('reports.account_manager') }}</th>
            <th>{{ __('reports.total_won_opportunity_value') }}</th>
            <th>{{ __('reports.won_opportunities_count') }}</th>
            <th>{{ __('reports.activities_count') }}</th>
            <th>{{ __('reports.activities_by_type') }}</th>
            <th>{{ __('reports.last_contacted_at') }}</th>
            <th>{{ __('reports.days_since_last_contact') }}</th>
            <th>{{ __('reports.next_follow_up_at') }}</th>
            <th>{{ __('reports.days_as_customer') }}</th>
            <th>{{ __('reports.converted_at') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($report['data'] as $row)
        <tr>
            <td>{{ $row['customer']['company_name'] }}</td>
            <td>{{ $row['customer']['contact_name'] }}</td>
            <td>{{ $row['customer']['status'] }}</td>
            <td>{{ $row['customer']['health_score'] }}</td>
            <td>{{ $row['account_manager']['name'] }}</td>
            <td>{{ collect($row['total_won_opportunity_value_by_currency'])->map(fn ($item) => $item['value'].' '.$item['currency'])->join(', ') }}</td>
            <td>{{ $row['won_opportunities_count'] }}</td>
            <td>{{ $row['activities_count'] }}</td>
            <td>{{ collect($row['activities_by_type'])->map(fn ($count, $type) => "{$type}: {$count}")->join(', ') }}</td>
            <td>{{ $row['last_contacted_at'] ?? '-' }}</td>
            <td>{{ $row['days_since_last_contact'] ?? '-' }}</td>
            <td>{{ $row['next_follow_up_at'] ?? '-' }}</td>
            <td>{{ $row['days_as_customer'] ?? '-' }}</td>
            <td>{{ $row['converted_at'] ?? '-' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="section-title">{{ __('reports.summary') }}</div>
<table class="summary-table">
    <tr><td>{{ __('reports.total_customers') }}</td><td>{{ $report['summary']['total_customers'] }}</td></tr>
    <tr><td>{{ __('reports.total_won_opportunity_revenue') }}</td><td>{{ collect($report['summary']['total_won_opportunity_revenue_by_currency'])->map(fn ($item) => $item['value'].' '.$item['currency'])->join(', ') }}</td></tr>
    <tr><td>{{ __('reports.avg_health_score') }}</td><td>{{ $report['summary']['avg_health_score'] }}</td></tr>
    <tr><td>{{ __('reports.churn_rate') }}</td><td>{{ $report['summary']['churn_rate'] }}%</td></tr>
    <tr><td>{{ __('reports.retention_rate') }}</td><td>{{ $report['summary']['retention_rate'] }}%</td></tr>
</table>

<div class="footer">{{ config('app.name') }}</div>

</body>
</html>
