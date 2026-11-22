<?php

namespace KusikusiCMS\Routes\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Api\Support\ApiException;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Routes\Abilities;
use KusikusiCMS\Routes\Api\Http\Requests\StoreRouteRequest;
use KusikusiCMS\Routes\Api\Http\Resources\EntityRouteResource;
use KusikusiCMS\Routes\EntityRoute;
use KusikusiCMS\Routes\Enums\RouteKind;
use KusikusiCMS\Routes\Services\RouteManager;
use KusikusiCMS\Routes\Support\ParsedPath;

/**
 * Routes of an entity and path resolution. Business rules (conflicts, path reclaiming) stay in RouteManager.
 */
class EntityRoutesController extends Controller
{
    /**
     * Every route of the entity (all languages and kinds), ?lang= to filter.
     */
    public function index(Request $request, string $entity): AnonymousResourceCollection
    {
        $request->validate(['lang' => ['sometimes', 'string', 'max:6']]);

        $model = Entity::query()->findOrFail($entity);
        Gate::authorize('view', $model);

        $routes = EntityRoute::query()
            ->where('entity_id', $model->id)
            ->when($request->filled('lang'), fn ($q) => $q->where('lang', $request->query('lang')))
            ->orderBy('lang')
            ->orderBy('kind')
            ->orderBy('path')
            ->get();

        return EntityRouteResource::collection($routes);
    }

    /**
     * Create an alias (default) or a redirect. A path used by another entity's primary or alias returns 409.
     */
    public function store(StoreRouteRequest $request, RouteManager $routes): JsonResponse
    {
        $route = $routes->createRoute(
            (string) $request->entity()->id,
            $request->string('lang', (string) Config::get('kusikusicms.models.default_language', 'en'))->toString(),
            $request->string('path')->toString(),
            RouteKind::from($request->string('kind', RouteKind::Alias->value)->toString()),
        );

        return EntityRouteResource::make($route)->response()->setStatusCode(201);
    }

    /**
     * Delete an alias or redirect. Primary routes are owned by the slug and return 409.
     */
    public function destroy(int $route): JsonResponse
    {
        $model = EntityRoute::query()->findOrFail($route);
        $entity = Entity::withTrashed()->findOrFail($model->entity_id);

        Gate::authorize(Abilities::DELETE_ROUTE, $entity);

        if ($model->kind === RouteKind::Primary) {
            throw new ApiException('Primary routes follow the slug and cannot be deleted.', 'conflict', 409);
        }

        $model->delete();

        return response()->json(null, 204);
    }

    /**
     * Resolve a request path (?path=/about.rss) to its route: owner entity, language, format and, for redirects,
     * the target primary path.
     */
    public function resolve(Request $request): JsonResponse
    {
        $request->validate(['path' => ['required', 'string', 'max:2048']]);

        $default = (string) config('kusikusicms.routes.default_format', 'html');
        $parsed = ParsedPath::fromUri($request->string('path')->toString(), $default);
        $route = EntityRoute::findByPath($parsed->path);

        if ($route === null) {
            throw new ApiException('No route matches the path.', 'not_found', 404);
        }

        Gate::authorize('view', Entity::query()->findOrFail($route->entity_id));

        $redirectTo = null;

        if ($route->kind->isRedirect()) {
            $primary = EntityRoute::query()
                ->where('entity_id', $route->entity_id)
                ->where('lang', $route->lang)
                ->where('kind', RouteKind::Primary->value)
                ->value('path');
            $redirectTo = $primary === null ? null : ParsedPath::toUri($primary, $parsed->format, $default);
        }

        return response()->json(['data' => array_merge(
            EntityRouteResource::make($route)->resolve($request),
            [
                'format' => $parsed->format,
                'status' => $route->kind->httpStatus(),
                'redirect_to' => $redirectTo,
            ],
        )]);
    }
}
