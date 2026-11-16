<?php

namespace KusikusiCMS\Acl;

class Acl
{
    private static int $authorizationBypassDepth = 0;

    public static function withoutAuthorization(callable $callback): mixed
    {
        self::$authorizationBypassDepth++;

        try {
            return $callback();
        } finally {
            self::$authorizationBypassDepth--;
        }
    }

    public static function isAuthorizationBypassed(): bool
    {
        return self::$authorizationBypassDepth > 0;
    }
}
