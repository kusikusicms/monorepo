# Design decisions

This document highlights key choices in the KusikusiCMS Routes package and the rationale behind them.

## 1) Synchronous listeners and a `RouteManager` service
- Listeners are thin; `RouteManager` (a container singleton) holds the logic. The same code serves automatic syncing and manual `EntityRoute::createFor()`, is easy to test, and can be extended and rebound.
- Syncing runs in the same request and transaction as the write, so a conflict can roll the write back. A queued job could not.

## 2) Validate in `saving`, write in `saved`
- `plan()` is read-only and runs on `EntityContentSaving` / `EntitySaving`; it throws before the row is written. `apply()` runs on `EntityContentSaved` / `EntitySaved`, inside a transaction.
- This gives rollback semantics even for a direct `EntityContent::create()` outside a transaction. `Entity::createContents()` also wraps its per-field saves in a transaction.

## 3) Walk `parent_entity_id`, not the ancestor relations
- Eloquent dispatches the custom `EntitySaved` event before observer callbacks, and the models `EntityObserver` refreshes `entities_relations` in `saved`. Listeners would see stale ancestors, so `PathBuilder` walks `parent_entity_id` directly.

## 4) Globally unique paths
- A unique index on `path` makes lookups one indexed query and keeps URLs unambiguous. Only one language can own a path (including `/`); other languages use explicit root slugs (`/es`).
- Consequence: one site per database in 13.x. `domains` is keyed by host for future multi-site support.
- Old redirects of another entity are reclaimed instead of raising conflicts, so freed URLs can be reused.

## 5) `RouteKind` as a PHP backed enum
- Stored as `string(32)` with an Eloquent enum cast, instead of a database `ENUM`, for SQLite/PostgreSQL portability. Values are kebab-case (`permanent-redirect`).

## 6) Denormalized model and view
- `entity_model` and `entity_view` are copied into each route, so a request needs no join to pick the controller method and view. They are refreshed when the entity's `model` or `view` changes.

## 7) A fallback catch-all route
- Registered with `->fallback()`, so app routes always win regardless of provider order. It can be disabled for apps with their own fallback.

## 8) Host controller base class and `ResolvedRoute`
- The app owns `WebsiteController`; the package owns the dispatch in the abstract `EntityRouteController`. Different short names avoid import aliases and IDE confusion.
- A readonly `ResolvedRoute` DTO replaces five positional arguments: typed, and extensible without breaking method signatures.
- Only public, non-static methods are dispatched, so helpers can stay `protected`.

## 9) Standard HTTP errors
- 404s use `abort(404)` and other failures bubble up as 500s, so the app's exception handler renders them. Path conflicts are a domain exception that apps map to validation errors.

## 10) Future directions
- Multi-site with per-domain uniqueness.
- Queued re-sync for very large trees, sitemaps and an admin UI for aliases and redirects.
