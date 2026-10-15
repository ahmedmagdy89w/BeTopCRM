<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = 'en';

        if ($user = $request->user()) {
            $locale = $user->locale ?? 'en';
        }

        // Allow frontend to override via Accept-Language header (en / ar only)
        $header = $request->header('Accept-Language');
        if ($header && in_array($header, ['en', 'ar'])) {
            $locale = $header;
        }

        // Allow direct links (PDF preview/download iframes, signed public URLs)
        // to request a locale explicitly — browsers don't send the app's
        // Accept-Language header on plain navigation.
        $query = $request->query('lang');
        if ($query && in_array($query, ['en', 'ar'])) {
            $locale = $query;
        }

        App::setLocale($locale);

        return $next($request);
    }
}
