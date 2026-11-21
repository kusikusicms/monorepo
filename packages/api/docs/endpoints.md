# Endpoints

All paths are under the configured prefix (`/api/v1`). Every route except `capabilities`, `auth/login` and `auth/session` requires authentication. Route names start with `kusikusicms.api.`.

## Auth and capabilities

| Method | Path | Notes |
|---|---|---|
| GET | `/capabilities` | Public, throttled (60/min) |
| POST | `/auth/login` | Token login |
| POST | `/auth/session` | SPA session login (when `auth.spa.enabled`) |
| POST | `/auth/logout` | Revokes the token or ends the session |
| GET | `/me` | The signed-in user |

## Entities

| Method | Path | Authorization |
|---|---|---|
| GET | `/entities` | `viewAny` |
| POST | `/entities` | `create` on the parent |
| GET | `/entities/{id}` | `view` |
| PATCH | `/entities/{id}` | `update` (+ `create` on the new parent when moved) |
| DELETE | `/entities/{id}` | `delete` (soft delete, 204) |
| POST | `/entities/{id}/restore` | `restore` |
| GET | `/entities/{id}/children` · `/descendants` · `/ancestors` · `/siblings` | `view` on `{id}` |
| GET | `/entities/{id}/tree` | `view`; `?depth=` (default 99) |
| GET | `/entities/{id}/contents` | `view`; `{data: {lang: {field: text}}}` |
| PUT | `/entities/{id}/contents/{lang}` | `update`; body `{field: text\|null}` |
| DELETE | `/entities/{id}/contents/{lang}/{field}` | `update` |
| GET | `/entities/{id}/relations` | `view`; non-ancestor relations, `?kind=` |
| POST | `/entities/{id}/relations` | `update`; `{called_entity_id, kind, position?, tags?}` |
| PATCH / DELETE | `/entities/{id}/relations/{calledId}/{kind}` | `update` |

### List parameters

`/entities` and the tree lists accept:

| Parameter | Effect |
|---|---|
| `model` | `page` or `page,post` |
| `child_of`, `descendant_of` (+`depth`), `ancestor_of`, `sibling_of` | Tree filters |
| `related_by`, `relating` (+`kind`, `tag`) | Relation filters (ancestor relations are excluded unless `kind` is given) |
| `live=1` | Published and inside the publish window |
| `with_contents=1`, `lang`, `fields=title,slug` | Load the contents of one language into `contents` |
| `include=contents,relations` | `contents_by_lang` (all languages) and `relations` |
| `sort` | Comma separated: `id`, `model`, `view`, `created_at`, `updated_at`, `publish_at`, `unpublish_at`, `content:{field}`; `-` for descending. Default `id` |
| `trashed` | `with` or `only` |
| `page`, `per_page` | Pagination |

### Writing

`POST /entities` accepts `{id?, model, view?, parent_entity_id?, props?, published?, publish_at?, unpublish_at?, langs?, contents?: {lang: {field: text}}}`. The entity and its contents are written in one transaction, entity first, so model events (route syncing, acl guard) run; any failure, such as a slug path conflict (409), rolls everything back. `PATCH` accepts the same fields except `id`. Writes never use mass updates, so every model event fires.

## Resource shape

```json
{
  "id": "about", "model": "page", "view": "page", "parent_entity_id": "home",
  "props": {}, "langs": ["en"], "published": true, "publish_at": "…", "unpublish_at": null,
  "status": "live", "created_at": "…", "updated_at": "…", "deleted_at": null,
  "contents": {"title": "About"},
  "contents_by_lang": {"en": {"title": "About"}},
  "relations": [{"caller_entity_id": "about", "called_entity_id": "team", "kind": "relation", "position": 0, "depth": 0, "tags": []}],
  "route": "/about",
  "can": {"view": true, "update": true, "delete": false, "create_child": true}
}
```

`contents` appears with `with_contents`/`lang`/`fields`, `contents_by_lang` and `relations` with `include`, `children` on `/tree`. `route` is added by `kusikusicms/routes` and `can` by `kusikusicms/acl`.

## Contributed endpoints

| Package | Method | Path |
|---|---|---|
| acl | GET | `/me/permissions` (user and guest role entity permissions, parsed) |
| routes | GET / POST | `/entities/{id}/routes` (alias or redirect; ability `kusikusicms.routes.create`) |
| routes | DELETE | `/routes/{routeId}` (ability `kusikusicms.routes.delete`; primary routes return 409) |
| routes | GET | `/routes/resolve?path=/about.rss` |
