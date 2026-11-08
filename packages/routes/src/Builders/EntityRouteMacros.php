<?php

namespace KusikusiCMS\Routes\Builders;

use KusikusiCMS\Models\Builders\EntityBuilder;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Routes\EntityRoute;

/**
 * Registers the route queries of the Entity query builder: withRoute() (mixed in from `EntityRouteQueries`), and the `routes` relationship of Entity.
 *
 * The macros are global because the models package cannot depend on the routes package.
 * Global macros are shared by every Eloquent builder, so withRoute() checks that the query is for an Entity.
 */
class EntityRouteMacros
{
    /**
     * Register the withRoute() macro on the Entity query builder and the `routes` relationship on Entity.
     */
    public static function register(): void
    {
        Entity::resolveRelationUsing('routes', fn (Entity $entity) => $entity->hasMany(EntityRoute::class, 'entity_id', 'id'));

        EntityBuilder::mixin(new EntityRouteQueries);
    }
}
