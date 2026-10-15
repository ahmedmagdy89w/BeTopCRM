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
        th { background-color: #b45309; color: #fff; padding: 7px 8px; text-align: {{ $isRtl ? 'right' : 'left' }}; }
        td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
        .footer { margin-top: 24px; font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>
@php($productLabels = \App\Services\CompanySettingsService::productLabels($locale ?? app()->getLocale(), __('reports.product'), __('reports.product')))
<h1>{{ \App\Services\CompanySettingsService::productReportTitle($locale ?? app()->getLocale()) }}</h1>
<div class="meta">{{ __('reports.generated_at') }}: {{ now()->format('Y-m-d H:i') }}</div>

<table>
    <thead><tr><th>{{ $productLabels['singular'] }}</th><th>{{ __('reports.category') }}</th><th>{{ __('reports.currency') }}</th><th>{{ __('reports.won_opportunities_count') }}</th><th>{{ __('reports.quantity') }}</th><th>{{ __('reports.booked_value') }}</th><th>{{ __('reports.open_opportunities') }}</th><th>{{ __('reports.pipeline_quantity') }}</th></tr></thead>
    <tbody>
        @foreach($report['data'] as $product)
            @forelse($product['booked_sales'] as $sale)
            <tr>
                <td>{{ $product['product']['name'] }}</td>
                <td>{{ $product['product']['category']['name'] ?? '-' }}</td>
                <td>{{ $sale['currency'] }}</td>
                <td>{{ $sale['opportunities_count'] }}</td>
                <td>{{ $sale['quantity'] }}</td>
                <td>{{ $sale['booked_value'] }}</td>
                <td>{{ $product['open_pipeline']['opportunities_count'] }}</td>
                <td>{{ $product['open_pipeline']['quantity'] }}</td>
            </tr>
            @empty
            <tr>
                <td>{{ $product['product']['name'] }}</td>
                <td>{{ $product['product']['category']['name'] ?? '-' }}</td>
                <td>-</td><td>0</td><td>0</td><td>0.00</td>
                <td>{{ $product['open_pipeline']['opportunities_count'] }}</td>
                <td>{{ $product['open_pipeline']['quantity'] }}</td>
            </tr>
            @endforelse
        @endforeach
    </tbody>
</table>

<div class="footer">{{ config('app.name') }}</div>
</body>
</html>