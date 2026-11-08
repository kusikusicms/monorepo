<?php

namespace App\Http\Controllers;

use App\Models\Home;
use Illuminate\Http\Request;
use Illuminate\View\View;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Routes\Http\Controllers\EntityRouteController;
use KusikusiCMS\Routes\Support\ResolvedRoute;

/**
 * Renders the entities reached through KusikusiCMS routes.
 *
 * How a request gets here:
 * 1. The kusikusicms/routes catch-all route finds the stored path (e.g. "/section/my-page")
 *    and checks that the entity is live (published, not trashed, within its publishing dates).
 * 2. handle() (inherited from EntityRouteController) calls the public method of this class named
 *    after the entity model in camelCase: "Home" → home(), "Section" → section(),
 *    "BlogPost" → blogPost(). Models without a method go to fallback(), which responds 404.
 *
 * Each method receives the request and a ResolvedRoute with:
 * - $route->entityId     the entity id
 * - $route->entityModel  the entity model ("Home")
 * - $route->entityView   the entity view ("home")
 * - $route->format       the format from the URL extension ("html" for "/page", "rss" for "/page.rss")
 * - $route->lang         the language of the route
 * - $route->path         the matched path ("/section/my-page")
 * - $route->route        the EntityRoute row (kind: primary or alias)
 *
 * Views follow the "{format}.{view}" convention: an entity with the "home" view requested as HTML
 * renders resources/views/html/home.blade.php, and as RSS resources/views/rss/home.blade.php.
 * $route->viewName() returns that name. Create a view for every model, view and format you serve,
 * otherwise Laravel throws a "View not found" error (a 500 response).
 */
class WebsiteController extends EntityRouteController
{
    /**
     * The home page: the entity, its live children.
     *
     * @param  Request  $request
     * @param  ResolvedRoute  $route
     * @noinspection PhpUnusedParameterInspection
     *
     * @return View
     */
    public function home(Request $request, ResolvedRoute $route): View
    {
        $entity = Home::withContents(['lang' => $route->lang])->findOrFail($route->entityId);
        $children = Home::query()
            ->withContents(['lang' => $route->lang])
            ->withRoute(['lang' => $route->lang])
            ->childrenOf($route->entityId)
            ->currentlyLive()
            ->get();

        return view($route->viewName(), [
            'entity' => $entity,
            'children' => $children,
            'lang' => $route->lang,
        ]);
    }

    /**
     * The section page: the entity, its live children.
     *
     * @param  Request  $request
     * @param  ResolvedRoute  $route
     * @noinspection PhpUnusedParameterInspection
     *
     * @return View
     */
    public function section(Request $request, ResolvedRoute $route): View
    {
        $entity = Entity::withContents(['lang' => $route->lang])->findOrFail($route->entityId);
        $children = Entity::withContents(['lang' => $route->lang])
            ->childrenOf($route->entityId)
            ->withRoute(['lang' => $route->lang])
            ->currentlyLive()
            ->get();

        return view($route->viewName(), [
            'entity' => $entity,
            'children' => $children,
            'lang' => $route->lang,
        ]);
    }

    /**
     * A single page: the entity contents.
     *
     * @param  Request  $request
     * @param  ResolvedRoute  $route
     * @noinspection PhpUnusedParameterInspection
     *
     * @return View
     */
    public function page(Request $request, ResolvedRoute $route): View
    {
        $entity = Entity::withContents(['lang' => $route->lang])->findOrFail($route->entityId);

        return view($route->viewName(), [
            'entity' => $entity,
            'lang' => $route->lang,
        ]);
    }

    // Add one method per model, for example:
    //
    // public function section(Request $request, ResolvedRoute $route): View
    // {
    //     $entity = Entity::withContents(['lang' => $route->lang])->findOrFail($route->entityId);
    //
    //     return view($route->viewName(), ['entity' => $entity, 'lang' => $route->lang]);
    // }
}
