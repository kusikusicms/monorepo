# KusikusiCMS Routes (Laravel 13)

> **Read-only split.** This repository is automatically split from [`packages/routes`](https://github.com/kusikusicms/monorepo/tree/main/packages/routes) in the [kusikusicms/monorepo](https://github.com/kusikusicms/monorepo). Please open issues and pull requests in the monorepo; pull requests sent here are closed automatically.
> Install it with `composer require kusikusicms/routes`.

Run-time routes for KusikusiCMS entities:
- Paths built from entity slugs (`/section/my-page`), stored in the `entities_routes` table.
- Kept in sync automatically: when a slug changes, the old path becomes a 301 redirect and every descendant follows.
- A catch-all route that resolves incoming URLs to an entity, model, view, format and language, and hands them to a controller in your app.

Version: 13.x

This package requires [`kusikusicms/models`](https://github.com/kusikusicms/models).

## Installation

1) Require the package:

```
composer require kusikusicms/routes
```

2) Run the installer. It asks for the primary domain (default: the `app.url` host), the language (default: `kusikusicms.models.default_language`) and the root entity id (default: `home`):

```
php artisan kusikusicms:routes:install
```

It creates `config/kusikusicms/routes.php`, `app/Http/Controllers/WebsiteController.php` and `resources/views/html/home.blade.php`. Existing files are kept unless you pass `--force`.

3) Run the migrations and remove any `/` route of your app (for example the default `welcome` route), because app routes win over the catch-all:

```
php artisan migrate
```

See [docs/installation.md](docs/installation.md) for options and `vendor:publish` tags.

## Configuration

All keys live under `kusikusicms.routes.*`:
- `models` (default `['*']`) and `except_models` (default `[]`): which entity models get routes.
- `slug_field` (default `slug`): the content field used to build paths.
- `domains`: the site, keyed by host, with `root_entity_id` (only its tree gets routes) and `lang`.
- `catch_all.enabled` (default `true`) and `catch_all.middleware` (default `['web']`).
- `controller` (default `App\Http\Controllers\WebsiteController`): the host controller.
- `formats` (default `html`, `rss`, `amp`, `json`, `xml`) and `default_format` (default `html`).

See [docs/configuration.md](docs/configuration.md).

## Quick start

```PHP
use KusikusiCMS\Models\Entity;

Entity::create(['id' => 'home', 'model' => 'Home'])->createContents(['title' => 'Home', 'slug' => '']);
Entity::create(['id' => 'blog', 'parent_entity_id' => 'home', 'model' => 'Section'])
    ->createContents(['title' => 'Blog', 'slug' => 'blog']);
Entity::create(['id' => 'p1', 'parent_entity_id' => 'blog', 'model' => 'BlogPost'])
    ->createContents(['title' => 'Hello', 'slug' => 'hello']);

// entities_routes now has "/" (home), "/blog" (blog) and "/blog/hello" (p1), all primary.

Entity::find('blog')->createContents(['slug' => 'news']);

// "/news" and "/news/hello" are the new primary routes;
// "/blog" and "/blog/hello" are permanent redirects to them.
```

Then add a method per model to your `WebsiteController`:

```PHP
public function blogPost(Request $request, ResolvedRoute $route): View
{
    $entity = Entity::withContents(['lang' => $route->lang])->findOrFail($route->entityId);

    return view($route->viewName(), ['entity' => $entity]); // "html.blog_post"
}
```

## Route kinds

The `kind` column is the `KusikusiCMS\Routes\Enums\RouteKind` enum:

| Kind | Value | Response |
|---|---|---|
| `Primary` | `primary` | The canonical path of an entity in a language (one per entity and language). Rendered by the host controller. |
| `Alias` | `alias` | Another path rendering the same entity. |
| `PermanentRedirect` | `permanent-redirect` | 301 to the primary path of the same entity and language. Created automatically when a slug changes. |
| `TemporaryRedirect` | `temporary-redirect` | 302 to the primary path of the same entity and language. |

The root entity always gets `/` in the site language, with no slug content needed. Paths are globally unique: only one entity and one language can use a path. Use an explicit slug for other language roots (for example the home slug `es` gives `/es`, `/es/blog`, ...).

## Manual API

```PHP
use KusikusiCMS\Routes\EntityRoute;
use KusikusiCMS\Routes\Enums\RouteKind;

EntityRoute::createFor('p1', 'en', '/hi', RouteKind::Alias);
EntityRoute::createFor('p1', 'en', '/promo', RouteKind::TemporaryRedirect);

$route = EntityRoute::findByPath('/blog/hello'); // or null
$route?->entity_id; // "p1"
$route?->lang;      // "en"
```

`createFor()` denormalizes the entity model and view, and throws `RoutePathConflictException` when another entity (or another language) already uses the path as primary or alias. The service behind it, `KusikusiCMS\Routes\Services\RouteManager`, is a container singleton you can extend and rebind.

## Host controller

The catch-all route parses the URL (`/blog/hello.rss` gives the path `/blog/hello` and the format `rss`), finds the route, checks that the entity is live and calls `handle()` on the configured controller. `handle()`, inherited from `KusikusiCMS\Routes\Http\Controllers\EntityRouteController`, calls the public method named after the model in camelCase (`Home` → `home()`, `BlogPost` → `blogPost()`) or `fallback()` (404 by default).

Views follow the `{format}.{view}` convention: `$route->viewName()` returns `html.home`, `rss.blog_post`, etc.

See [docs/request-handling.md](docs/request-handling.md).

## Errors

- Unknown paths and formats, entities that are not live and redirects without a primary respond 404 through `abort(404)`. A missing view or any other bug is a 500. Your app's exception handler renders them.
- `RoutePathConflictException` (with `path`, `conflictingEntityId` and `lang`) is thrown when a slug change or a move would reuse a taken path. The content or entity write is rolled back.

See [docs/errors.md](docs/errors.md).

## Testing

```shell
cd packages/routes
../../vendor/bin/phpunit
```

See [docs/testing.md](docs/testing.md).

## Documentation
- [docs/installation.md](docs/installation.md)
- [docs/configuration.md](docs/configuration.md)
- [docs/route-syncing.md](docs/route-syncing.md)
- [docs/request-handling.md](docs/request-handling.md)
- [docs/errors.md](docs/errors.md)
- [docs/testing.md](docs/testing.md)
- [docs/design-decisions.md](docs/design-decisions.md)

## License

MIT. See [LICENSE](LICENSE).
