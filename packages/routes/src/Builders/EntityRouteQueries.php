<?php

namespace KusikusiCMS\Routes\Builders;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use KusikusiCMS\Models\Builders\EntityBuilder;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Routes\EntityRoute;
use KusikusiCMS\Routes\Enums\RouteKind;
use LogicException;

/**
 * Query methods the routes package adds to the Entity query builder.
 *
 * It is mixed into `EntityBuilder` by `EntityRouteMacros::register()`: every public method returns the
 * closure that is bound to the builder. Each closure runs with `$this` as the builder.
 * Global macros are shared by every Eloquent builder, so each one checks that the query is for an Entity.
 */
class EntityRouteQueries
{
    /**
     * Add a `route` attribute with the primary path of the entity in a language (null when it has none).
     * The paths are loaded after the query in one extra query, so the selected columns never change.
     *
     * Accepts a language code or an options array like `['lang' => 'es']`. Defaults to the
     * `kusikusicms.models.default_language` language.
     *
     * @return Closure(string|array<string, mixed>|null): mixed
     */
    public function withRoute(): Closure
    {
        /** @param  string|array<string, mixed>|null  $lang */
        return function (string|array|null $lang = null) {
            // The mixin binds this closure to the builder, so $this is an EntityBuilder at run time.
            /** @var EntityBuilder $this */
            /** @var Model $model */
            $model = $this->getModel(); // @phpstan-ignore varTag.nativeType
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
        };
    }
}
