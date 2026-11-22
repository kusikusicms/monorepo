<?php

namespace KusikusiCMS\Routes;

/**
 * Gate abilities owned by the routes package. They receive the Entity whose routes change.
 *
 * The routes package never decides who may use them: kusikusicms/acl registers handlers (mapped to `update` on
 * the entity) and the application may define its own. Without a handler, kusikusicms/api denies them by default.
 */
final class Abilities
{
    /**
     * Create an alias or redirect route for an entity.
     */
    public const CREATE_ROUTE = 'kusikusicms.routes.create';

    /**
     * Delete a non-primary route of an entity.
     */
    public const DELETE_ROUTE = 'kusikusicms.routes.delete';
}
