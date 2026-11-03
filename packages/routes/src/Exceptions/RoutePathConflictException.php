<?php

namespace KusikusiCMS\Routes\Exceptions;

use RuntimeException;

/**
 * Thrown when a path is already used as a primary route or alias by another entity,
 * or by the same entity in another language. Paths are globally unique.
 *
 * This is a domain exception, not an HTTP one: apps (or the admin) can map it to a validation error.
 */
class RoutePathConflictException extends RuntimeException
{
    public function __construct(
        public readonly string $path,
        public readonly string $conflictingEntityId,
        public readonly string $lang,
    ) {
        parent::__construct(sprintf(
            'The path [%s] is already used by entity [%s] in language [%s].',
            $path,
            $conflictingEntityId,
            $lang,
        ));
    }
}
