<!DOCTYPE html>
<html dir="{{ $isRtl ? 'rtl' : 'ltr' }}" lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; margin: 20px; color: #333; direction: {{ $isRtl ? 'rtl' : 'ltr' }}; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { font-size: 10px; color: #888; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background-color: #0f766e; color: #fff; padding: 7px 8px; text-align: {{ $isRtl ? 'right' : 'left' }}; }
        td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
        .footer { margin-top: 24px; font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>
<h1>{{ __('reports.lead_pipeline_title') }}</h1>
<div class="meta">{{ __('reports.generated_at') }}: {{ now()->format('Y-m-d H:i') }}</div>

<table>
    <thead><tr><th>{{ __('reports.lead_status') }}</th><th>{{ __('reports.total_leads') }}</th></tr></thead>
    <tbody>
        @foreach($report['data']['by_status'] as $status => $count)
        <tr><td>{{ __('reports.status_'.$status) }}</td><td>{{ $count }}</td></tr>
        @endforeach
    </tbody>
</table>

<table>
    <thead><tr><th>{{ __('reports.source') }}</th><th>{{ __('reports.total_leads') }}</th><th>{{ __('reports.avg_health_score') }}</th></tr></thead>
    <tbody>
        @foreach($report['data']['by_source'] as $source)
        <tr><td>{{ $source['source'] ?? __('reports.unknown_source') }}</td><td>{{ $source['lead_count'] }}</td><td>{{ $source['avg_score'] ?? '-' }}</td></tr>
        @endforeach
    </tbody>
</table>

<table>
    <thead><tr><th>{{ __('reports.owner') }}</th><th>{{ __('reports.total_leads') }}</th><th>{{ __('reports.avg_health_score') }}</th></tr></thead>
    <tbody>
        @foreach($report['data']['by_owner'] as $owner)
        <tr><td>{{ $owner['owner']['name'] ?? __('reports.unassigned') }}</td><td>{{ $owner['lead_count'] }}</td><td>{{ $owner['avg_score'] ?? '-' }}</td></tr>
        @endforeach
    </tbody>
</table>

<div class="footer">{{ config('app.name') }}</div>
</body>
</html>