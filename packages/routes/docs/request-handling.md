# Request handling

## The catch-all route

The package registers one route (in `routes/web.php`):

```PHP
Route::middleware(config('kusikusicms.routes.catch_all.middleware'))
    ->get('{path?}', RoutesController::class)
    ->where('path', '.*')
    ->fallback()
    ->name('kusikusicms.routes.catch-all');
```

It is a Laravel *fallback* route, so it is matched after every other route, whatever the provider order. Routes defined by your app always win. Disable it with `catch_all.enabled = false`.

## Parsing the URL

`KusikusiCMS\Routes\Support\ParsedPath::fromUri()` turns the URL into a stored path and a format. The extension of the last segment is the format, `index` is dropped, and repeated or trailing slashes are ignored:

| URL | Path | Format |
|---|---|---|
| `/my-section/my-path` | `/my-section/my-path` | `html` |
| `/my-section/my-path.html` | `/my-section/my-path` | `html` |
| `/my-section/my-path/index.html` | `/my-section/my-path` | `html` |
| `/my-section/my-path/` | `/my-section/my-path` | `html` |
| `/my-section/my-path.rss` | `/my-section/my-path` | `rss` |
| `/my-section/my-path/index.amp` | `/my-section/my-path` | `amp` |
| `/`, `/index.html` | `/` | `html` |

Extensions are lower-cased, and URL-encoded characters are decoded (`/secci%C3%B3n` matches `/sección`). A format not listed in `formats` responds 404.

## Resolving the route

`RoutesController` looks up the path with one indexed query (`EntityRoute::findByPath()`), then:

| Route found | Response |
|---|---|
| None | 404 |
| `permanent-redirect` | 301 to the `primary` route of the same entity and language |
| `temporary-redirect` | 302 to the `primary` route of the same entity and language |
| Redirect without a primary | 404 |
| `primary` or `alias` of an entity that is trashed, unpublished, scheduled or expired | 404 |
| `primary` or `alias` of a live entity | The host controller's `handle()` |

Redirects keep the query string and a non-default format: `/old.rss?page=2` redirects to `/new.rss?page=2`. The root keeps it as `/index.rss`.

"Live" is the `currentlyLive()` filter of `kusikusicms/models`: `published` is true, `publish_at` is in the past and `unpublish_at` is empty or in the future.

## The host controller

The configured `controller` (by default `App\Http\Controllers\WebsiteController`, published by the installer) must extend `KusikusiCMS\Routes\Http\Controllers\EntityRouteController`. Its `handle(Request $request, ResolvedRoute $route)`:

1. calls the public method named after the entity model in camelCase: `Home` → `home()`, `Section` → `section()`, `BlogPost` → `blogPost()`;
2. or `fallback($request, $route)` when there is none. By default `fallback()` responds 404; override it to render a generic view.

Every method receives the request and a `KusikusiCMS\Routes\Support\ResolvedRoute`:

| Property | Example |
|---|---|
| `entityId` | `p1` |
| `entityModel` | `BlogPost` |
| `entityView` | `blog_post` |
| `format` | `html`, `rss`, ... |
| `lang` | `en` |
| `path` | `/blog/hello` (the requested path, which can be an alias) |
| `route` | The `EntityRoute` row |

```PHP
use Illuminate\Http\Request;
use Illuminate\View\View;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Routes\Http\Controllers\EntityRouteController;
use KusikusiCMS\Routes\Support\ResolvedRoute;

class WebsiteController extends EntityRouteController
{
    public function home(Request $request, ResolvedRoute $route): View
    {
        $entity = Entity::withContents(['lang' => $route->lang])->findOrFail($route->entityId);
        $sections = Entity::withContents(['lang' => $route->lang])
            ->childrenOf($route->entityId)
            ->currentlyLive()
            ->get();

        return view($route->viewName(), compact('entity', 'sections') + ['lang' => $route->lang]);
    }
}
```

## Views

Views follow the `{format}.{view}` convention, returned by `$route->viewName()`:

| Entity view | Format | View name | File |
|---|---|---|---|
| `home` | `html` | `html.home` | `resources/views/html/home.blade.php` |
| `blog_post` | `rss` | `rss.blog_post` | `resources/views/rss/blog_post.blade.php` |

Create a view for every view and format you serve. A missing view is a Laravel "View not found" error (500).

## Using your own fallback

If your app defines its own `Route::fallback()`, set `catch_all.enabled` to `false` and route to the controller yourself:

```PHP
use KusikusiCMS\Routes\Http\Controllers\RoutesController;

Route::fallback(RoutesController::class);
```
