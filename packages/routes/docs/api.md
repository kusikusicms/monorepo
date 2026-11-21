# JSON API contribution

When [`kusikusicms/api`](../../api/README.md) is installed, routes adds to it (disable with `KUSIKUSICMS_ROUTES_API=false` / `kusikusicms.routes.api.enabled`):

- A **`route` field** on every entity resource: the primary path in `?lang=` (or `kusikusicms.models.default_language`), loaded with `withRoute()` in one extra query per response.
- **Endpoints** (under `/api/v1`, authenticated):

| Method | Path | Notes |
|---|---|---|
| GET | `/entities/{id}/routes` | Every route of the entity, `?lang=` to filter. Needs `view` |
| POST | `/entities/{id}/routes` | `{path, lang?, kind?}` where `kind` is `alias` (default), `permanent-redirect` or `temporary-redirect`. Ability `kusikusicms.routes.create` |
| DELETE | `/routes/{routeId}` | Ability `kusikusicms.routes.delete`. Primary routes follow the slug and return 409 |
| GET | `/routes/resolve?path=/about.rss` | Route, format, HTTP status and `redirect_to` for redirects |

- `RoutePathConflictException` renders as `409 {code: "conflict"}`.
- The capability `routes` with the features `route-field`, `entity-routes`, `resolve`.

## Authorization

The abilities are constants of `KusikusiCMS\Routes\Abilities` and receive the entity. routes never decides who may use them: `kusikusicms/acl` maps them to `update` on the entity, or define your own:

```php
Gate::define(Abilities::CREATE_ROUTE, fn (User $user, Entity $entity) => $user->isAdmin());
```

Without a handler they are denied by the API's deny-by-default rule.
