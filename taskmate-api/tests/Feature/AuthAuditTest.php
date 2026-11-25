<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

function daySevenIsProtected($route): bool
{
    return collect($route->gatherMiddleware())->contains(
        fn (mixed $middleware): bool => $middleware === 'auth:sanctum'
            || (is_string($middleware) && str_contains($middleware, 'Authenticate:sanctum')),
    );
}

it('requires sanctum authentication on every api route except the documented public endpoints', function () {
    $publicRoutes = [
        'POST api/v1/auth/register',
        'POST api/v1/auth/login',
        'POST api/v1/auth/password/forgot',
        'POST api/v1/auth/password/reset',
        'POST api/v1/auth/email/verify',
        'GET api/v1/auth/{provider}/redirect',
        'GET api/v1/auth/{provider}/callback',
        'GET api/v1/health',
    ];

    $apiRoutes = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1'))
        ->values();

    expect($apiRoutes->count())->toBeGreaterThan(40);

    foreach ($apiRoutes as $route) {
        foreach ($route->methods() as $method) {
            if ($method === 'HEAD') {
                continue;
            }

            $key = "{$method} {$route->uri()}";
            $isProtected = daySevenIsProtected($route);

            expect($isProtected)->toBe(
                ! in_array($key, $publicRoutes, true),
                "Unexpected auth middleware for {$key}.",
            );
        }
    }
});

it('returns 401 for every protected api route when unauthenticated', function () {
    $apiRoutes = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1'))
        ->filter(fn ($route): bool => daySevenIsProtected($route))
        ->values();

    expect($apiRoutes)->not->toBeEmpty();

    foreach ($apiRoutes as $route) {
        $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());

        foreach ($route->methods() as $method) {
            if ($method === 'HEAD') {
                continue;
            }

            $this->json($method, $uri)->assertUnauthorized();
        }
    }
});

it('keeps the health endpoint public and database free', function () {
    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);

    expect(DB::getQueryLog())->toBeEmpty();
});
