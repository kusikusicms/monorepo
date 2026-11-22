<?php

namespace KusikusiCMS\Acl;

use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Traits\HasRoles;

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

    /**
     * Whether the user holds the manage permission (directly or through a role), which allows managing roles and user roles.
     */
    public static function canManage(?Authenticatable $user): bool
    {
        if ($user === null || ! method_exists($user, 'checkPermissionTo')) {
            return false;
        }

        try {
            return $user->checkPermissionTo(self::managePermission(), (string) config('kusikusicms.acl.guard_name'));
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    /**
     * The name of the manage permission. It must not look like an entity permission.
     *
     * @throws InvalidArgumentException When the name starts with the entity permission prefix
     */
    public static function managePermission(): string
    {
        $name = (string) config('kusikusicms.acl.manage_permission', 'kusikusicms.acl.manage');
        $prefix = (string) config('kusikusicms.acl.prefix', 'entities');

        if ($name === $prefix || str_starts_with($name, $prefix.'.')) {
            throw new InvalidArgumentException("The manage permission [{$name}] must not start with the entity prefix [{$prefix}].");
        }

        return $name;
    }

    /**
     * The user model of the acl guard (`kusikusicms.acl.guard_name`), resolved from its user provider,
     * or null when it cannot be resolved.
     *
     * @return class-string|null
     */
    public static function userModel(): ?string
    {
        $guard = (string) config('kusikusicms.acl.guard_name', 'web');
        $provider = config('auth.guards.'.$guard.'.provider') ?? config('auth.guards.'.config('auth.defaults.guard').'.provider');
        $model = config('auth.providers.'.$provider.'.model');

        return is_string($model) && class_exists($model) ? $model : null;
    }

    /**
     * Whether the user endpoints of the api contribution are available: `kusikusicms.acl.api.users.enabled`
     * is true and the user model uses Spatie's HasRoles.
     */
    public static function usersApiEnabled(): bool
    {
        $model = self::userModel();

        return (bool) config('kusikusicms.acl.api.users.enabled', true)
            && $model !== null
            && in_array(HasRoles::class, class_uses_recursive($model), true);
    }
}
