<?php

namespace KusikusiCMS\Api\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Sanctum\NewAccessToken;
use LogicException;

/**
 * Creates a Sanctum personal access token, honouring the configured token expiration (minutes, null = never).
 */
final class IssueToken
{
    /**
     * Issue a token for the user named after the device.
     */
    public function __invoke(Authenticatable $user, string $deviceName): NewAccessToken
    {
        if (! method_exists($user, 'createToken')) {
            throw new LogicException('The user model must use the Laravel\Sanctum\HasApiTokens trait.');
        }

        $minutes = config('kusikusicms.api.auth.token_expiration');

        return $user->createToken(
            $deviceName,
            ['*'],
            $minutes === null ? null : CarbonImmutable::now()->addMinutes((int) $minutes),
        );
    }
}
