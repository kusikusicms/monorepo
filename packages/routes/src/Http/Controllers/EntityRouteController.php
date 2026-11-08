<?php

namespace KusikusiCMS\Routes\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use KusikusiCMS\Routes\Exceptions\ModelMethodNotFoundException;
use KusikusiCMS\Routes\Support\ResolvedRoute;
use ReflectionMethod;

/**
 * Base class of the host application controller that renders resolved routes
 * (configured in kusikusicms.routes.controller, e.g. App\Http\Controllers\WebsiteController).
 *
 * handle() calls the public method named after the entity model in camelCase
 * ("Home" → home(), "BlogPost" → blogPost()) with the request and the ResolvedRoute,
 * or fallback() when there is no such method.
 */
abstract class EntityRouteController
{
    /**
     * Entry point called by the package RoutesController for primary and alias routes.
     */
    public function handle(Request $request, ResolvedRoute $route): mixed
    {
        $method = $this->methodFor($route);
        if ($method === null) {
            $methodName = self::methodNameFor($route);

            return $this->fallback($request, $route, $methodName);
        }

        return $this->{$method}($request, $route);
    }

    /**
     * Called when the controller has no method for the entity model. Responds 404 by default;
     * override it to render a generic view.
     */
    public function fallback(Request $request, ResolvedRoute $route, string $method): mixed
    {
        throw new ModelMethodNotFoundException($method);
    }

    /**
     * The public method handling an entity model, or null when there is none.
     */
    protected function methodFor(ResolvedRoute $route): ?string
    {
        $method = self::methodNameFor($route);
        if (! method_exists($this, $method)) {
            return null;
        }
        $reflection = new ReflectionMethod($this, $method);

        return $reflection->isPublic() && ! $reflection->isStatic() ? $method : null;
    }

    /**
     * The name of the public method that should handle an entity model.
     */
    protected function methodNameFor(ResolvedRoute $route): ?string
    {
        $method = Str::camel($route->entityModel);
        if ($method === '' || in_array(strtolower($method), ['handle', 'fallback', 'methodfor'], true)) {
            return null;
        }

        return $method;
    }
}
