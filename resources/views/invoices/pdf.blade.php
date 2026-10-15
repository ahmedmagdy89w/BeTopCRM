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
        .right { text-align: right; }
        .totals { width: 260px; {{ app()->getLocale() === 'ar' ? 'margin: 18px auto 0 0;' : 'margin: 18px 0 0 auto;' }} }
        .totals td { padding: 4px 0; }
        .total { border-top: 1px solid #d1d5db; font-weight: bold; font-size: 14px; }
        .status { display: inline-block; padding: 3px 8px; border: 1px solid #9ca3af; border-radius: 10px; }
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
                    {{ __('messages.invoice_tax_id') }}: {{ $company['company_tax_number'] }}
                @endif
            </td>
            <td class="document-heading right">
                <h1>{{ __('messages.invoice_title') }}</h1>
                <div class="muted">{{ $invoice->invoice_number }}</div>
                <div class="status">{{ __('messages.invoice_status') }}: {{ $invoice->status }}</div>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td>
                <span class="meta-label">{{ __('messages.invoice_to') }}</span>
                <strong>{{ $buyer['name'] }}</strong><br>
                @if ($buyer['contact_name'] && $buyer['contact_name'] !== $buyer['name'])
                    {{ __('messages.invoice_contact') }}: {{ $buyer['contact_name'] }}<br>
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
                    {{ __('messages.invoice_tax_id') }}: {{ $buyer['tax_id'] }}
                @endif
            </td>
            <td class="right">
                <span class="meta-label">{{ __('messages.invoice_issued_on') }}</span>
                <strong>{{ $invoice->issued_at?->format('Y-m-d') }}</strong><br>
                <span class="meta-label">{{ __('messages.invoice_opportunity') }}</span>
                <strong>{{ $invoice->opportunity->title }}</strong>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>{{ __('messages.invoice_item') }}</th>
                <th>{{ __('messages.invoice_sku') }}</th>
                <th class="right">{{ __('messages.invoice_quantity') }}</th>
                <th class="right">{{ __('messages.invoice_unit_price') }}</th>
                <th class="right">{{ __('messages.invoice_item_discount') }}</th>
                <th class="right">{{ __('messages.invoice_item_tax') }}</th>
                <th class="right">{{ __('messages.invoice_subtotal') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $item->sku ?: '—' }}</td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ $invoice->currency }} {{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right">{{ $invoice->currency }} {{ number_format((float) $item->discount_amount, 2) }}{{ $item->discount_percent ? ' ('.number_format((float) $item->discount_percent, 2).'%)' : '' }}</td>
                    <td class="right">{{ $item->tax_name ? $item->tax_name.' ('.number_format((float) $item->tax_percent, 2).'%)' : '—' }}</td>
                    <td class="right">{{ $invoice->currency }} {{ number_format((float) $item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        @include('pdf.partials.totals-row', ['rtl' => $rtl, 'label' => __('messages.invoice_subtotal'), 'value' => $invoice->currency.' '.number_format((float) $invoice->subtotal, 2)])
        @include('pdf.partials.totals-row', ['rtl' => $rtl, 'label' => __('messages.invoice_discount'), 'value' => $invoice->currency.' '.number_format((float) $invoice->discount_amount, 2)])
        @include('pdf.partials.totals-row', ['rtl' => $rtl, 'label' => __('messages.invoice_tax'), 'value' => $invoice->currency.' '.number_format((float) $invoice->tax_amount, 2)])
        @include('pdf.partials.totals-row', ['rtl' => $rtl, 'total' => true, 'label' => __('messages.invoice_total'), 'value' => $invoice->currency.' '.number_format((float) $invoice->total, 2)])
    </table>
</body>
</html>
