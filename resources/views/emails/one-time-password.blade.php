<p>{{ __('messages.one_time_password_intro') }}</p>

<p style="font-size: 28px; font-weight: 700; letter-spacing: 8px;">{{ $code }}</p>

<p>{{ __('messages.one_time_password_expiry', ['minutes' => $expiresInMinutes]) }}</p>

<p>{{ __('messages.one_time_password_ignore') }}</p>
