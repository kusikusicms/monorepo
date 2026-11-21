<?php

namespace KusikusiCMS\Api\Services;

use Closure;
use Illuminate\Http\Request;
use KusikusiCMS\Models\Builders\EntityBuilder;

/**
 * Query modifiers registered by other packages (for example routes adds withRoute(), acl filters lists with
 * whereUserCan()). They run on every entity query the API builds, before it is executed.
 *
 * Context is 'index' for lists (including tree lists) and 'show' for single entities.
 */
final class EntityQuery
{
    /** @var array<int, Closure(EntityBuilder, Request, string): mixed> */
    private static array $modifiers = [];

    /**
     * Register a modifier: fn (EntityBuilder $query, Request $request, string $context): void.
     */
    public static function extend(Closure $modifier): void
    {
        self::$modifiers[] = $modifier;
    }

    /**
     * Apply every registered modifier in registration order.
     */
    public static function apply(EntityBuilder $query, Request $request, string $context): EntityBuilder
    {
        foreach (self::$modifiers as $modifier) {
            $modifier($query, $request, $context);
        }

        return $query;
    }

    /**
     * Remove every modifier (tests).
     */
    public static function flush(): void
    {
        self::$modifiers = [];
    }
}
