<?php

namespace KusikusiCMS\Api\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use KusikusiCMS\Api\Support\ApiException;

/**
 * Checks credentials through the configured guard's user provider, with a throttle per email and IP.
 * Shared by token login and, later, session login.
 */
final class AttemptLogin
{
    /**
     * @return Authenticatable Validated user.
     *
     * @throws ApiException 422 invalid_credentials, or 429 too_many_requests once the throttle is exhausted.
     */
    public function __invoke(string $email, string $password, Request $request): Authenticatable
    {
        [$maxAttempts, $decayMinutes] = $this->throttle();
        $key = Str::transliterate(Str::lower($email).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new ApiException('Too many login attempts. Try again later.', 'too_many_requests', 429);
        }

        $provider = Auth::createUserProvider(config('auth.guards.'.$this->guard().'.provider'));
        $user = $provider?->retrieveByCredentials(['email' => $email]);

        if ($user === null || ! $provider->validateCredentials($user, ['password' => $password])) {
            RateLimiter::hit($key, $decayMinutes * 60);

            throw ApiException::invalidCredentials();
        }

        RateLimiter::clear($key);

        return $user;
    }

    /**
     * The guard whose user provider checks credentials; null uses the application default.
     */
    public function guard(): string
    {
        return config('kusikusicms.api.auth.guard') ?? config('auth.defaults.guard');
    }

    /**
     * Parse the "max,minutes" throttle, for example "5,1".
     *
     * @return array{0: int, 1: int}
     */
    protected function throttle(): array
    {
        [$maxAttempts, $decayMinutes] = array_pad(
            explode(',', (string) config('kusikusicms.api.auth.login_throttle', '5,1')),
            2,
            '1',
        );

        return [max(1, (int) $maxAttempts), max(1, (int) $decayMinutes)];
    }
}
