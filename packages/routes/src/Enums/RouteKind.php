<?php

namespace KusikusiCMS\Routes\Enums;

/**
 * The kind of a stored route.
 *
 * - Primary: the canonical path of an entity in a language (one per entity and language).
 * - Alias: another path that renders the same entity.
 * - PermanentRedirect: redirects (301) to the primary path of the same entity and language.
 * - TemporaryRedirect: redirects (302) to the primary path of the same entity and language.
 */
enum RouteKind: string
{
    case Primary = 'primary';
    case Alias = 'alias';
    case PermanentRedirect = 'permanent-redirect';
    case TemporaryRedirect = 'temporary-redirect';

    /**
     * Whether requests to this route are redirected instead of rendered.
     */
    public function isRedirect(): bool
    {
        return match ($this) {
            self::PermanentRedirect, self::TemporaryRedirect => true,
            self::Primary, self::Alias => false,
        };
    }

    /**
     * The HTTP redirect status code, or null when the route is rendered.
     */
    public function httpStatus(): ?int
    {
        return match ($this) {
            self::PermanentRedirect => 301,
            self::TemporaryRedirect => 302,
            self::Primary, self::Alias => null,
        };
    }
}
