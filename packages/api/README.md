# KusikusiCMS API (Laravel 13)

> **Read-only split.** This repository is automatically split from [`packages/api`](https://github.com/kusikusicms/monorepo/tree/main/packages/api) in the [kusikusicms/monorepo](https://github.com/kusikusicms/monorepo). Please open issues and pull requests in the monorepo; pull requests sent here are closed automatically.
> Install it with `composer require kusikusicms/api`.

A versioned JSON API (`/api/v1`) for KusikusiCMS, so the CMS can run headless and an admin SPA has a backend. This package holds the shared plumbing: the route group, Sanctum authentication, the error envelope, pagination, capabilities and the extension hooks used by other packages.

## Quick start

```bash
composer require kusikusicms/api
```

Add Sanctum's `HasApiTokens` trait to your user model:

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
}
```

Log in with a personal access token:

```bash
curl -X POST http://localhost/api/v1/auth/login \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"ada@example.com","password":"secret","device_name":"cli"}'

curl http://localhost/api/v1/me -H 'Accept: application/json' -H 'Authorization: Bearer <token>'
```

## Configuration

Publish the config with `php artisan vendor:publish --tag=kusikusicms-api-config` and adjust `config/kusikusicms/api.php`:

| Key | Default | Purpose |
|---|---|---|
| `prefix` | `api/v1` | URL prefix of the API |
| `auth.token_expiration` | `null` | Minutes until a token expires (`null` = never) |
| `auth.login_throttle` | `5,1` | Login attempts per email and IP |
| `pagination.per_page` / `max_per_page` | `25` / `100` | List page size and cap |
| `authorization.unhandled.read` / `write` | `allow` / `deny` | Behaviour when no Gate handler answers |

A first-party SPA can instead sign in with a session cookie: `GET /sanctum/csrf-cookie`, then `POST /api/v1/auth/session` from a domain listed in `SANCTUM_STATEFUL_DOMAINS`. See [authentication](docs/authentication.md).

## Features

- Entity CRUD, restore, contents, relations and tree queries, with filters, sorting and length-aware pagination.
- Token and SPA cookie authentication through Sanctum, with a shared login throttle.
- One error envelope `{message, code, errors?}` for every API error.
- Deny-by-default authorization: writes without a Gate handler or policy return 403.
- `GET /capabilities` for clients to discover installed packages and features.
- Hooks for other packages: routes, resource fields, query modifiers, capabilities. `kusikusicms/acl` adds `can` flags and `GET /me/permissions`; `kusikusicms/routes` adds a `route` field and route endpoints.

## Documentation

- [Installation and configuration](docs/installation.md)
- [Authentication (tokens and SPA cookies)](docs/authentication.md)
- [Endpoints and resource shape](docs/endpoints.md)
- [Errors and pagination](docs/errors-and-pagination.md)
- [Extending the API](docs/extending.md)

## License

MIT. See [LICENSE](LICENSE).
