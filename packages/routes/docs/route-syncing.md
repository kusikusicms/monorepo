# Route syncing

Routes are created, redirected and removed automatically when slug contents and entities change. Everything runs synchronously, in the same request and database transaction as the write.

## What triggers a sync

| Event (from `kusikusicms/models`) | Listener | Action |
|---|---|---|
| `EntityContentSaving` | `ValidateSlugRoutes` | For a new or changed slug: plan the new paths (read-only) and throw on conflict, before the row is written. |
| `EntityContentSaved` | `SyncSlugRoutes` | For a new or changed slug: plan and apply. |
| `EntityContentDeleted` | `SyncSlugRoutes` | For a slug: remove the primary route of that language and cascade. |
| `EntitySaving` | `SyncEntityRoutes::validate` | When `parent_entity_id` changes: validate the new paths in every language. |
| `EntitySaved` | `SyncEntityRoutes::handle` | When `parent_entity_id` changes: re-sync in every language. When `model` or `view` changes: update the denormalized `entity_model` and `entity_view` of the entity's routes. |

Every content write path fires these events, including `Entity::createContents()`, which saves one `EntityContent` per field. Mass query updates (`EntityContent::where(...)->update()`) skip model events and do not sync routes.

Ignored writes:
- fields other than `slug_field`;
- a slug saved with the same text;
- entities outside the `root_entity_id` tree (unless they still own routes, for example after being moved out of the tree: their primary routes are removed);
- models not routable (`models` / `except_models`). Their own route is skipped, but their descendants are still synced.

## Building a path

`PathBuilder` walks up `parent_entity_id` from the entity to the root entity and joins the slugs of the same language:

- The root entity (or an entity without parent when no root is configured) contributes its slug, or nothing when the slug is empty or missing: `""` gives `/`, `es` gives `/es`.
- The configured root entity needs no slug content in the domain language (`domains.*.lang`): a missing slug counts as empty, so it always gets `/`. The route is created when the root entity is created (or its model or view changes), and by `kusikusicms:routes:install` when the root already exists. Deleting its slug content keeps `/`. Other languages need an explicit slug (for example `es`).
- Every other entity in the chain needs a non-empty slug. Otherwise no path can be built, and the entity has no primary route in that language.
- Slugs are trimmed of whitespace and `/`. A slug containing `/` in the middle throws `InvalidArgumentException`.

Example in `es`: `home` (slug `""`) > `s1` (`seccion`) > `p1` (`pagina`) gives `/seccion/pagina`. With a home slug `es`, it gives `/es/seccion/pagina`.

## Plan and apply

`RouteManager` works in two phases:

1. `plan($entity, $lang)` computes the primary path of the entity and of all its descendants (breadth first, two queries per tree level) and checks every path. It writes nothing.
2. `apply($plan)` writes the plan inside a transaction. For each entity:
   - no path: its primary route in that language is deleted;
   - same path as its current primary: only the model and view are refreshed;
   - new path: the current primary becomes a `permanent-redirect`, and the new path becomes the primary. If the entity already had a redirect (or alias) at that path, for example after reverting a slug, that row is promoted back to primary instead of creating a duplicate.

## Conflicts

Paths are globally unique (a unique index on `path`). A planned path conflicts when it is used as a `primary` or `alias`:
- by another entity, or
- by the same entity in another language (only one language can own `/`).

Then `RoutePathConflictException` is thrown and nothing is written: the slug content is not saved, the entity move is not saved, and no route rows change. A conflict anywhere in the cascade aborts the whole operation.

Old `permanent-redirect` and `temporary-redirect` rows of another entity are not conflicts: they are reclaimed (deleted) when the path is needed.

## Deleting entities

- Force-deleting an entity removes its routes through the foreign key cascade.
- Soft-deleted or unpublished entities keep their routes, so restoring or publishing them brings the URLs back. The catch-all responds 404 for them meanwhile.

## Manual syncing

```PHP
use KusikusiCMS\Routes\Services\RouteManager;

app(RouteManager::class)->syncEntity('home');        // every language, with descendants
app(RouteManager::class)->syncEntity('home', 'es');  // one language
```

## Performance notes
- A slug change on an entity with many descendants re-paths all of them synchronously. This is fine for typical sites; very large trees may need a queued re-sync in a future version.
- Lookups at request time are one indexed query on `path`.
