<?php

namespace KusikusiCMS\Api;

use Closure;
use Illuminate\Support\Facades\Route;
use KusikusiCMS\Api\Http\Middleware\ForceJsonResponse;
use KusikusiCMS\Api\Support\Capabilities;
use KusikusiCMS\Api\Support\Capability;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

final class Api
{
    /**
     * The API version exposed by the capabilities endpoint.
     */
    public const VERSION = 'v1';

    /**
     * Register a package entry for GET /capabilities. Registering the same name again replaces it.
     *
     * @param  array<int, string>  $features
     */
    public static function capability(string $name, string $version, array $features = []): void
    {
        app(Capabilities::class)->register(new Capability($name, $version, $features));
    }

    /**
     * Register routes inside the configured API group: prefix, middleware and the 'kusikusicms.api.' name prefix.
     * When SPA auth is enabled, Sanctum's stateful middleware runs first; it only starts a session (and checks CSRF)
     * for requests coming from a `sanctum.stateful` domain, so token clients are unaffected.
     */
    public static function routes(Closure $routes): void
    {
        Route::prefix(self::prefix())
            ->middleware(self::middleware())
            ->name('kusikusicms.api.')
            ->group($routes);
    }

    /**
     * The configured URL prefix, without surrounding slashes.
     */
    public static function prefix(): string
    {
        return trim((string) config('kusikusicms.api.prefix', 'api/v1'), '/');
    }

    /**
     * The middleware applied to every API route.
     *
     * @return array<int, string>
     */
    public static function middleware(): array
    {
        $middleware = [ForceJsonResponse::class];

        if (self::spaEnabled()) {
            $middleware[] = EnsureFrontendRequestsAreStateful::class;
        }

        return array_values(array_unique(array_merge($middleware, (array) config('kusikusicms.api.middleware', ['api']))));
    }

    /**
     * Whether SPA cookie/session authentication is enabled.
     */
    public static function spaEnabled(): bool
    {
        return (bool) config('kusikusicms.api.auth.spa.enabled', true);
    }
}
