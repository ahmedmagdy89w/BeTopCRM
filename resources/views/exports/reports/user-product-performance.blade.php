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
        th { background-color: #1d4ed8; color: #fff; padding: 7px 8px; text-align: {{ $isRtl ? 'right' : 'left' }}; }
        td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
        .footer { margin-top: 24px; font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>
@php($productLabels = \App\Services\CompanySettingsService::productLabels($locale ?? app()->getLocale(), __('reports.product'), __('reports.products')))
<h1>{{ __('reports.user_product_performance_title') }}</h1>
<div class="meta">{{ __('reports.generated_at') }}: {{ now()->format('Y-m-d H:i') }}</div>

<table>
    <thead><tr><th>{{ __('reports.user') }}</th><th>{{ $productLabels['singular'] }}</th><th>{{ __('reports.sku') }}</th><th>{{ __('reports.currency') }}</th><th>{{ __('reports.total_opportunities') }}</th><th>{{ __('reports.won_opportunities_count') }}</th><th>{{ __('reports.open_opportunities') }}</th><th>{{ __('reports.quantity') }}</th><th>{{ __('reports.booked_value') }}</th></tr></thead>
    <tbody>
        @foreach($report['data'] as $row)
            @forelse($row['booked_value_by_currency'] as $sale)
            <tr>
                <td>{{ $row['user']['name'] }}</td>
                <td>{{ $row['product']['name'] }}</td>
                <td>{{ $row['product']['sku'] ?? '-' }}</td>
                <td>{{ $sale['currency'] }}</td>
                <td>{{ $row['opportunities_count'] }}</td>
                <td>{{ $row['won_count'] }}</td>
                <td>{{ $row['open_count'] }}</td>
                <td>{{ $row['quantity'] }}</td>
                <td>{{ $sale['value'] }}</td>
            </tr>
            @empty
            <tr>
                <td>{{ $row['user']['name'] }}</td>
                <td>{{ $row['product']['name'] }}</td>
                <td>{{ $row['product']['sku'] ?? '-' }}</td>
                <td>-</td>
                <td>{{ $row['opportunities_count'] }}</td>
                <td>{{ $row['won_count'] }}</td>
                <td>{{ $row['open_count'] }}</td>
                <td>{{ $row['quantity'] }}</td>
                <td>0.00</td>
            </tr>
            @endforelse
        @endforeach
    </tbody>
</table>

<div class="footer">{{ config('app.name') }}</div>
</body>
</html>
