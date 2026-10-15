<!DOCTYPE html>
<html dir="{{ $isRtl ? 'rtl' : 'ltr' }}" lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: landscape; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; margin: 20px; color: #333; direction: {{ $isRtl ? 'rtl' : 'ltr' }}; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { font-size: 9px; color: #888; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background-color: #047857; color: #fff; padding: 7px 8px; text-align: {{ $isRtl ? 'right' : 'left' }}; }
        td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
        .footer { margin-top: 24px; font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>
<h1>{{ __('reports.sales_title') }}</h1>
<div class="meta">{{ __('reports.generated_at') }}: {{ now()->format('Y-m-d H:i') }}</div>

<table>
    <thead><tr><th>{{ __('reports.period') }}</th><th>{{ __('reports.currency') }}</th><th>{{ __('reports.revenue') }}</th><th>{{ __('reports.won_opportunities_count') }}</th><th>{{ __('reports.open_opportunities') }}</th><th>{{ __('reports.open_value') }}</th></tr></thead>
    <tbody>
        @foreach($report['data'] as $row)
            @forelse($row['revenue_by_currency'] as $sale)
            <tr>
                <td>{{ $row['period'] }}</td>
                <td>{{ $sale['currency'] }}</td>
                <td>{{ $sale['value'] }}</td>
                <td>{{ $row['won_count'] }}</td>
                <td>{{ $row['open_count'] }}</td>
                <td>{{ $row['open_value'] }}</td>
            </tr>
            @empty
            <tr>
                <td>{{ $row['period'] }}</td>
                <td>-</td>
                <td>0.00</td>
                <td>{{ $row['won_count'] }}</td>
                <td>{{ $row['open_count'] }}</td>
                <td>{{ $row['open_value'] }}</td>
            </tr>
            @endforelse
        @endforeach
    </tbody>
</table>

<div class="footer">{{ config('app.name') }}</div>
</body>
</html>
