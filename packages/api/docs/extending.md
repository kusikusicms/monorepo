# Extending the API

Domain packages contribute endpoints instead of `api` knowing about them. `acl` and `routes` use the same hooks; they only `suggest` `kusikusicms/api` and register their contribution from their provider's `boot()`:

```php
if (class_exists(Api::class) && $this->app->bound(Capabilities::class) && config('kusikusicms.mypackage.api.enabled', true)) {
    MyApiContribution::register($version);
}
```

## Routes

```php
Api::routes(fn () => require __DIR__.'/../routes/api.php');
```

The closure runs inside the API group: prefix, middleware and the `kusikusicms.api.` name prefix. Wrap protected routes in `Route::middleware(config('kusikusicms.api.auth_middleware'))`.

## Capabilities

```php
Api::capability('mypackage', $version, ['feature-a', 'feature-b']);
```

`GET /capabilities` lists `{name, version, features}` per package plus `api.version` and `api.auth` (`modes`: `token`, and `session` with `csrf_cookie_url` when SPA auth is on). A client should check features rather than versions.

## Resource fields

```php
EntityResource::extend(
    fn (Entity $entity, Request $request) => ['my_field' => $entity->getAttribute('my_field')],
    fn (Collection $entities, Request $request) => /* load data for every entity at once */,
);
```

The `prepare` callback runs once per response with every entity of it (a page, a tree with its nested children, or one entity) before the fields. Use it to batch queries; the number of queries must not grow with the page size.

## Query modifiers

```php
EntityQuery::extend(function (EntityBuilder $query, Request $request, string $context) {
    // $context is 'index' (lists and trees) or 'show' (one entity)
    $query->withRoute($request->query('lang'));
});
```

Modifiers run on every entity query the API builds, before it is executed.

## Authorization

Ask through `Gate`, never through Spatie or permission names. Define ability names as constants in the owning package (for example `KusikusiCMS\Routes\Abilities::CREATE_ROUTE = 'kusikusicms.routes.create'`) and let `acl` or the application register handlers. Without a handler, `kusikusicms.*` writes are denied by default. Keep business invariants in your services and translate their exceptions with `ApiErrorRenderer::map()`.

## Tests

The hooks are static. Call `EntityResource::flushExtensions()` and `EntityQuery::flush()` in `tearDown()`.
