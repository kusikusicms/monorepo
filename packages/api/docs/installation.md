# Installation

```bash
composer require kusikusicms/api
php artisan vendor:publish --tag=sanctum-migrations
php artisan migrate
```

`kusikusicms/api` requires `kusikusicms/models`, `kusikusicms/acl` and `laravel/sanctum`. The provider is auto-discovered.

Add Sanctum's trait to the user model (next to acl's `HasRoles`):

```php
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles;
}
```

Publish the configuration when you need to change it:

```bash
php artisan vendor:publish --tag=kusikusicms-api-config   # config/kusikusicms/api.php
```

| Key | Default | Purpose |
|---|---|---|
| `prefix` | `api/v1` (`KUSIKUSICMS_API_PREFIX`) | URL prefix of every endpoint |
| `middleware` | `['api']` | Middleware of the route group |
| `auth_middleware` | `['auth:sanctum']` | Middleware of the protected routes |
| `auth.guard` | `null` | Guard whose user provider checks credentials (`null` = default guard) |
| `auth.token_expiration` | `null` | Minutes until a personal access token expires (`null` = never) |
| `auth.login_throttle` | `5,1` | Login attempts per email and IP, per minutes |
| `auth.spa.enabled` | `true` (`KUSIKUSICMS_API_SPA`) | SPA cookie/session authentication |
| `auth.spa.guard` | `web` | Session guard the SPA logs into; must be one of `sanctum.guard` |
| `auth.spa.remember` | `true` | Whether `remember: true` is honoured |
| `pagination.per_page` / `max_per_page` | `25` / `100` | Default and maximum page size |
| `authorization.unhandled.read` / `write` | `allow` / `deny` | Answer when no Gate handler or policy answers (see below) |

`php artisan about` shows the prefix, the unhandled policy, whether SPA auth is on, and warns when `auth.spa.guard` is not one of `sanctum.guard`.

## Deny by default

Every endpoint authorizes through Laravel's `Gate`. With `kusikusicms/acl` installed, `EntityPolicy` answers the entity abilities. When nothing answers (for example an app that disabled acl's policy, or a package ability without a handler), the API falls back to `authorization.unhandled`: reads (`viewAny`, `view`) are allowed for authenticated users and writes are denied. The fallback only applies to `kusikusicms.*` abilities and to Entity abilities, never to the application's own abilities.

## Optional contributions

`kusikusicms/acl` and `kusikusicms/routes` add their own endpoints and fields when this package is installed. Disable them with `kusikusicms.acl.api.enabled` / `kusikusicms.routes.api.enabled` (`KUSIKUSICMS_ACL_API`, `KUSIKUSICMS_ROUTES_API`).
