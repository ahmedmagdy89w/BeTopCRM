<?php

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

function runSetLocale(?string $queryLang, ?string $headerLang, ?string $userLocale): string
{
    App::setLocale('en');

    $request = Request::create(
        $queryLang ? "/api/v1/x?lang={$queryLang}" : '/api/v1/x',
        'GET',
        [],
        [],
        [],
        $headerLang ? ['HTTP_ACCEPT_LANGUAGE' => $headerLang] : []
    );

    if ($userLocale !== null) {
        $user = User::factory()->create(['locale' => $userLocale]);
        $request->setUserResolver(fn () => $user);
    }

    (new SetLocale)->handle($request, fn () => response('ok'));

    return App::getLocale();
}

test('lang query param wins over header and user locale', function () {
    expect(runSetLocale('ar', 'en', 'en'))->toBe('ar')
        ->and(runSetLocale('en', 'ar', 'ar'))->toBe('en');
});

test('header wins over user locale without query param', function () {
    expect(runSetLocale(null, 'ar', 'en'))->toBe('ar');
});

test('user locale applies without overrides', function () {
    expect(runSetLocale(null, null, 'ar'))->toBe('ar');
});

test('invalid lang values are ignored', function () {
    expect(runSetLocale('fr', null, 'en'))->toBe('en')
        ->and(runSetLocale('ar-SA', null, 'en'))->toBe('en');
});

test('defaults to english', function () {
    expect(runSetLocale(null, null, null))->toBe('en');
});
