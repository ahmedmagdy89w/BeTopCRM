<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        h1 { color: #173b67; margin-bottom: 4px; }
        .muted { color: #6b7280; }
        .header { width: 100%; border-bottom: 2px solid #173b67; padding-bottom: 16px; margin-bottom: 24px; }
        .company { width: 65%; vertical-align: top; }
        .document-heading { width: 35%; vertical-align: top; }
        .logo { max-height: 72px; max-width: 180px; margin-bottom: 8px; }
        .company-name { font-size: 15px; font-weight: bold; color: #173b67; }
        .meta { width: 100%; margin-bottom: 24px; }
        .meta td { vertical-align: top; width: 50%; }
        .meta-label { display: block; margin-bottom: 5px; color: #173b67; }
        table.items { width: 100%; border-collapse: collapse; }
        .items th { background: #173b67; color: white; padding: 9px; text-align: left; }
        .items td { border-bottom: 1px solid #e5e7eb; padding: 9px; }
        .right { text-align: right !important; }
        .totals { width: 42%; margin-top: 18px; border-collapse: collapse; {{ app()->getLocale() === 'ar' ? 'margin-right: auto;' : 'margin-left: auto;' }} }
        .totals td { padding: 5px; }
        .total { border-top: 2px solid #173b67; font-size: 15px; font-weight: bold; }
        .section { margin-top: 28px; }
    </style>
</head>
<body>
    @php
        $company = $document['company'];
        $buyer = $document['buyer'];
        $rtl = app()->getLocale() === 'ar';
        $companyLocality = implode(', ', array_filter([$company['address']['city'], $company['address']['state'], $company['address']['zip']]));
        $buyerLocality = implode(', ', array_filter([$buyer['address']['city'], $buyer['address']['state'], $buyer['address']['postal_code']]));
    @endphp

    <table class="header">
        <tr>
            <td class="company">
                @if (! empty($company['logo_file_path']) && is_file($company['logo_file_path']))
                    <img class="logo" src="{{ $company['logo_file_path'] }}" alt="{{ $company['company_name'] }}">
                @endif
                <div class="company-name">{{ $company['company_name'] ?: config('app.name') }}</div>
                @if ($company['address']['street'])
                    {{ $company['address']['street'] }}<br>
                @endif
                @if ($companyLocality)
                    {{ $companyLocality }}<br>
                @endif
                @if ($company['address']['country'])
                    {{ $company['address']['country'] }}<br>
                @endif
                @if ($company['company_email'])
                    {{ $company['company_email'] }}<br>
                @endif
                @if ($company['company_phone'])
                    {{ $company['company_phone'] }}<br>
                @endif
                @if ($company['company_website'])
                    {{ $company['company_website'] }}<br>
                @endif
                @if ($company['company_tax_number'])
                    {{ __('messages.quotation_tax_id') }}: {{ $company['company_tax_number'] }}
                @endif
            </td>
            <td class="document-heading right">
                <h1>{{ __('messages.quotation_title') }}</h1>
                <div class="muted">{{ $quotation->quotation_number }}</div>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td>
                <span class="meta-label">{{ __('messages.quotation_to') }}</span>
                <strong>{{ $buyer['name'] }}</strong><br>
                @if ($buyer['contact_name'] && $buyer['contact_name'] !== $buyer['name'])
                    {{ __('messages.quotation_contact') }}: {{ $buyer['contact_name'] }}<br>
                @endif
                @if ($buyer['email'])
                    {{ $buyer['email'] }}<br>
                @endif
                @if ($buyer['phone'])
                    {{ $buyer['phone'] }}<br>
                @endif
                @if ($buyer['address']['line_1'])
                    {{ $buyer['address']['line_1'] }}<br>
                @endif
                @if ($buyer['address']['line_2'])
                    {{ $buyer['address']['line_2'] }}<br>
                @endif
                @if ($buyerLocality)
                    {{ $buyerLocality }}<br>
                @endif
                @if ($buyer['address']['country'])
                    {{ $buyer['address']['country'] }}<br>
                @endif
                @if ($buyer['tax_id'])
                    {{ __('messages.quotation_tax_id') }}: {{ $buyer['tax_id'] }}
                @endif
            </td>
            <td class="right">
                <span class="meta-label">{{ __('messages.quotation_opportunity') }}</span>
                <strong>{{ $quotation->opportunity->title }}</strong><br>
                {{ __('messages.quotation_valid_until') }}: {{ $quotation->valid_until?->format('Y-m-d') }}
            </td>
        </tr>
    </table>

    <table class="items">
        <thead><tr><th>{{ __('messages.quotation_item') }}</th><th>{{ __('messages.quotation_sku') }}</th><th class="right">{{ __('messages.quotation_quantity') }}</th><th class="right">{{ __('messages.quotation_unit_price') }}</th><th class="right">{{ __('messages.quotation_item_discount') }}</th><th class="right">{{ __('messages.quotation_item_tax') }}</th><th class="right">{{ __('messages.quotation_subtotal') }}</th></tr></thead>
        <tbody>
        @foreach ($quotation->items as $item)
            <tr><td>{{ $item->product_name }}</td><td>{{ $item->sku ?: '—' }}</td><td class="right">{{ $item->quantity }}</td><td class="right">{{ $quotation->currency }} {{ number_format($item->unit_price, 2) }}</td><td class="right">{{ $quotation->currency }} {{ number_format($item->discount_amount, 2) }}{{ $item->discount_percent ? ' ('.number_format($item->discount_percent, 2).'%)' : '' }}</td><td class="right">{{ $item->tax_name ? $item->tax_name.' ('.number_format($item->tax_percent, 2).'%)' : '—' }}</td><td class="right">{{ $quotation->currency }} {{ number_format($item->subtotal, 2) }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        @include('pdf.partials.totals-row', ['rtl' => $rtl, 'label' => __('messages.quotation_subtotal'), 'value' => $quotation->currency.' '.number_format($quotation->subtotal, 2)])
        @include('pdf.partials.totals-row', ['rtl' => $rtl, 'label' => __('messages.quotation_discount'), 'value' => $quotation->currency.' '.number_format($quotation->discount_amount, 2)])
        @include('pdf.partials.totals-row', ['rtl' => $rtl, 'label' => __('messages.quotation_tax'), 'value' => $quotation->currency.' '.number_format($quotation->tax_amount, 2)])
        @include('pdf.partials.totals-row', ['rtl' => $rtl, 'total' => true, 'label' => __('messages.quotation_total'), 'value' => $quotation->currency.' '.number_format($quotation->total, 2)])
    </table>

    @if ($quotation->notes)
        <div class="section"><strong>{{ __('messages.quotation_notes') }}</strong><br>{{ $quotation->notes }}</div>
    @endif
    @if ($quotation->terms ?: __('messages.quotation_default_terms'))
        <div class="section"><strong>{{ __('messages.quotation_terms') }}</strong><br>{{ $quotation->terms ?: __('messages.quotation_default_terms') }}</div>
    @endif
</body>
</html>
