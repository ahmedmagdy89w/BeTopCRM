@php
    $company = $document['company'];
    $buyer = $document['buyer'];
    $companyLocality = implode(', ', array_filter([$company['address']['city'], $company['address']['state'], $company['address']['zip']]));
@endphp

@if ($company['logo_url'])
    <img src="{{ $company['logo_url'] }}" alt="{{ $company['company_name'] }}" width="180">
@endif
<p><strong>{{ $company['company_name'] ?: config('app.name') }}</strong></p>
@if ($company['address']['street'] || $companyLocality || $company['address']['country'])
    <p>
        @if ($company['address']['street'])<>{{ $company['address']['street'] }}<br>@endif
        @if ($companyLocality){{ $companyLocality }}<br>@endif
        @if ($company['address']['country']){{ $company['address']['country'] }}<br>@endif
        @if ($company['company_email']){{ $company['company_email'] }}<br>@endif
        @if ($company['company_phone']){{ $company['company_phone'] }}@endif
    </p>
@endif

<p>{{ __('messages.quotation_email_greeting', ['name' => $buyer['contact_name'] ?: $buyer['name']]) }}</p>
<p>{{ __('messages.quotation_email_intro', ['number' => $quotation->quotation_number]) }}</p>
<p>{{ __('messages.quotation_email_attachment') }}</p>
<p>{{ __('messages.quotation_email_expiry', ['date' => $quotation->valid_until?->format('Y-m-d')]) }}</p>
