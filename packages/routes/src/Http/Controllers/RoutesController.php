<?php

namespace KusikusiCMS\Routes\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Routes\EntityRoute;
use KusikusiCMS\Routes\Enums\RouteKind;
use KusikusiCMS\Routes\Support\ParsedPath;
use KusikusiCMS\Routes\Support\ResolvedRoute;
use LogicException;

/**
 * The catch-all controller: resolves the request path to a stored route.
 *
 * - Unknown paths or formats, and entities that are not live, respond 404.
 * - Redirect routes respond 301/302 to the primary route of the same entity and language,
 *   keeping a non-default format and the query string.
 * - Primary and alias routes are handed to the configured host controller's handle() method.
 */
class RoutesController
{
    public function __invoke(Request $request): mixed
    {
        $default = (string) config('kusikusicms.routes.default_format', 'html');
        $parsed = ParsedPath::fromUri($request->getPathInfo(), $default);
        $formats = array_map('strtolower', (array) config('kusikusicms.routes.formats', [$default]));
        if (! in_array($parsed->format, $formats, true)) {
            abort(404);
        }

        $route = EntityRoute::findByPath($parsed->path);
        if ($route === null) {
            abort(404);
        }

        if ($route->kind->isRedirect()) {
            return $this->redirect($request, $route, $parsed, $default);
        }

        $live = Entity::query()->currentlyLive()->whereKey($route->entity_id)->exists();
        if (! $live) {
            abort(404);
        }

        return $this->hostController()->handle($request, new ResolvedRoute(
            entityId: $route->entity_id,
            entityModel: $route->entity_model,
            entityView: $route->entity_view,
            format: $parsed->format,
            lang: $route->lang,
            path: $parsed->path,
            route: $route,
        ));
    }

    /**
     * Redirect to the primary route of the same entity and language.
     */
    protected function redirect(Request $request, EntityRoute $route, ParsedPath $parsed, string $default): RedirectResponse
    {
        $primary = EntityRoute::query()
            ->where('entity_id', $route->entity_id)
            ->where('lang', $route->lang)
            ->where('kind', RouteKind::Primary->value)
            ->first();
        if ($primary === null) {
            abort(404);
        }

        $target = ParsedPath::toUri($primary->path, $parsed->format, $default);
        $query = $request->getQueryString();

        return redirect()->to($target.($query ? '?'.$query : ''), (int) $route->kind->httpStatus());
    }

    /**
     * The configured host controller.
     *
     * @throws LogicException When it does not extend EntityRouteController
     */
    protected function hostController(): EntityRouteController
    {
        $class = (string) config('kusikusicms.routes.controller');
        $controller = app($class);
        if (! $controller instanceof EntityRouteController) {
            throw new LogicException("The routes controller [{$class}] must extend ".EntityRouteController::class.'.');
        }

        return $controller;
    }
}
