<?php

namespace KusikusiCMS\Routes\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use KusikusiCMS\Routes\Http\Controllers\EntityRouteController;
use KusikusiCMS\Routes\Support\ResolvedRoute;

/**
 * Host controller used by the HTTP tests: echoes the called method and the resolved route.
 */
class TestWebsiteController extends EntityRouteController
{
    public function home(Request $request, ResolvedRoute $route): JsonResponse
    {
        return $this->echo('home', $route);
    }

    public function blogPost(Request $request, ResolvedRoute $route): JsonResponse
    {
        return $this->echo('blogPost', $route);
    }

    public function page(Request $request, ResolvedRoute $route): JsonResponse
    {
        return $this->echo('page', $route);
    }

    protected function section(Request $request, ResolvedRoute $route): JsonResponse
    {
        return $this->echo('section', $route);
    }

    protected function echo(string $method, ResolvedRoute $route): JsonResponse
    {
        return new JsonResponse([
            'method' => $method,
            'entityId' => $route->entityId,
            'entityModel' => $route->entityModel,
            'entityView' => $route->entityView,
            'format' => $route->format,
            'lang' => $route->lang,
            'path' => $route->path,
            'kind' => $route->route->kind->value,
            'view' => $route->viewName(),
        ]);
    }
}
