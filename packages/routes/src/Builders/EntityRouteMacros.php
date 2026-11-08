<?php

namespace KusikusiCMS\Routes\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use KusikusiCMS\Models\Builders\EntityBuilder;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Routes\EntityRoute;
use KusikusiCMS\Routes\Enums\RouteKind;
use LogicException;

/**
 * Registers the route macros of the Entity query builder: withRoute(), and the `routes` relationship of Entity.
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

        EntityBuilder::macro('withRoute', function (string|array|null $lang = null) {
            /** @var Model $model */
            $model = $this->getModel();
            if (! $model instanceof Entity) {
                throw new LogicException('withRoute() can only be used on Entity queries.');
            }

            $lang = (is_array($lang) ? ($lang['lang'] ?? null) : $lang) ?? Config::get('kusikusicms.models.default_language', 'en');

            return $this->afterQuery(function (Collection $entities) use ($lang) {
                if ($entities->isEmpty()) {
                    return $entities;
                }

                $paths = EntityRoute::query()
                    ->whereIn('entity_id', $entities->map(fn (Entity $entity) => $entity->getKey())->all())
                    ->where('lang', $lang)
                    ->where('kind', RouteKind::Primary)
                    ->pluck('path', 'entity_id');

                $entities->each(function (Entity $entity) use ($paths) {
                    $entity->setAttribute('route', $paths->get($entity->getKey()));
                    $entity->syncOriginalAttribute('route');
                });

                return $entities;
            });
        });
    }
}
