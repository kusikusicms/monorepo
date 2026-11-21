<?php

namespace KusikusiCMS\Routes\Api;

use Illuminate\Http\Request;
use KusikusiCMS\Api\Api;
use KusikusiCMS\Api\Http\Resources\EntityResource;
use KusikusiCMS\Api\Services\EntityQuery;
use KusikusiCMS\Api\Support\ApiErrorRenderer;
use KusikusiCMS\Models\Builders\EntityBuilder;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Routes\Builders\EntityRouteQueries;
use KusikusiCMS\Routes\Exceptions\RoutePathConflictException;

/**
 * What routes adds to kusikusicms/api. Registered by RoutesServiceProvider only when the api package is installed
 * and `kusikusicms.routes.api.enabled` is true.
 *
 * - A `route` field (the primary path in `?lang=`, or the default language) on every EntityResource, loaded with
 *   withRoute() in one extra query per response.
 * - Entity route endpoints and path resolution (routes/api.php), authorized with KusikusiCMS\Routes\Abilities.
 * - RoutePathConflictException renders as 409 `conflict`.
 */
final class RoutesApiContribution
{
    public static function register(string $version): void
    {
        EntityQuery::extend(function (EntityBuilder $query, Request $request): void {
            // Same as $query->withRoute($lang), called through the mixin so static analysis sees it.
            (new EntityRouteQueries)->withRoute()->call($query, $request->query('lang'));
        });

        EntityResource::extend(fn (Entity $entity) => ['route' => $entity->getAttribute('route')]);

        ApiErrorRenderer::map(RoutePathConflictException::class, 409, 'conflict');

        Api::routes(fn () => require __DIR__.'/../../routes/api.php');

        Api::capability('routes', $version, ['route-field', 'entity-routes', 'resolve']);
    }
}
