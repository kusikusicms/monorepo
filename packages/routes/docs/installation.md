# Installation

## Requirements
- PHP 8.4, Laravel 13.
- [`kusikusicms/models`](https://github.com/kusikusicms/models) (installed as a dependency).

## Install

```
composer require kusikusicms/routes
php artisan kusikusicms:routes:install
php artisan migrate
```

The service provider `KusikusiCMS\Routes\RoutesServiceProvider` is auto-discovered. It merges the config into `kusikusicms.routes`, loads the `entities_routes` migration, registers the route syncing listeners and the catch-all route, and adds an entry to `php artisan about`.

## The install command

`php artisan kusikusicms:routes:install` (alias `k:routes:install`) asks for:

| Prompt | Option | Default |
|---|---|---|
| Primary domain | `--domain=` | The host of `app.url` (`APP_URL`), or `localhost` |
| Primary language | `--lang=` | `kusikusicms.models.default_language` |
| Root entity id | `--root=` | `home` (an empty answer means every entity gets routes) |

Then it writes:
- `config/kusikusicms/routes.php`, with the answers in the `domains` entry;
- `app/Http/Controllers/WebsiteController.php`, the host controller (see [request-handling.md](request-handling.md));
- `resources/views/html/home.blade.php`, a working home view.

Existing files are skipped unless `--force` is given. With `--no-interaction` the defaults are used.

## Publish tags

`vendor:publish` cannot ask questions, so the published config uses `APP_URL`, `KUSIKUSICMS_DOMAIN` and `KUSIKUSICMS_LANG`:

| Tag | Publishes |
|---|---|
| `kusikusicms-config` | The config of every KusikusiCMS package, including `config/kusikusicms/routes.php` |
| `kusikusicms-routes-config` | `config/kusikusicms/routes.php` |
| `kusikusicms-routes-migrations` | The `entities_routes` migration, for apps that customise it |
| `kusikusicms-routes-stubs` | `WebsiteController` and `html/home.blade.php` |

## After installing
- Remove any app route for `/` (such as the default `welcome` route). App routes always win over the catch-all, so it would hide the CMS home.
- If your app already defines `Route::fallback()`, disable the catch-all (`catch_all.enabled = false`) and call `KusikusiCMS\Routes\Http\Controllers\RoutesController` from your own fallback.
- Existing content: routes are created when slug contents are written. For data imported before installing, re-sync with `app(RouteManager::class)->syncEntity($rootId)` (it cascades to all descendants).
