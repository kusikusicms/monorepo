<?php

namespace KusikusiCMS\Api\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\TransientToken;

/**
 * Ends the current authentication, whichever mode the request used: a bearer token is revoked; a cookie session
 * (Sanctum's TransientToken) is logged out of the session guard, invalidated and given a new CSRF token.
 */
final class Logout
{
    public function __invoke(Request $request): void
    {
        $user = $request->user();
        $token = $user !== null && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;

        if ($token !== null && ! $token instanceof TransientToken) {
            $token->delete();

            return;
        }

        if ($request->hasSession()) {
            Auth::guard((string) config('kusikusicms.api.auth.spa.guard', 'web'))->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
}
