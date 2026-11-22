<?php

namespace KusikusiCMS\Api\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use KusikusiCMS\Api\Support\ApiException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/**
 * Signs a first-party SPA in with a session cookie: checks credentials with AttemptLogin (same throttle and errors
 * as token login), logs into the configured session guard and regenerates the session id (no fixation).
 */
final class StartSession
{
    public function __construct(private AttemptLogin $attempt) {}

    /**
     * @throws ApiException 422 stateful_required when the request is not from a `sanctum.stateful` domain.
     */
    public function __invoke(string $email, string $password, bool $remember, Request $request): Authenticatable
    {
        if (! self::isStateful($request)) {
            throw new ApiException(
                'Session login requires a request from a stateful domain (sanctum.stateful).',
                'stateful_required',
                422,
            );
        }

        $user = ($this->attempt)($email, $password, $request);

        Auth::guard((string) config('kusikusicms.api.auth.spa.guard', 'web'))
            ->login($user, $remember && (bool) config('kusikusicms.api.auth.spa.remember', true));

        $request->session()->regenerate();

        return $user;
    }

    /**
     * Whether Sanctum treated the request as coming from the first-party frontend and started a session.
     */
    public static function isStateful(Request $request): bool
    {
        return $request->attributes->get('sanctum') === true
            && $request->hasSession()
            && EnsureFrontendRequestsAreStateful::fromFrontend($request);
    }
}
