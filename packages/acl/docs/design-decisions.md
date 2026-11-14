# Design decisions

## Why Spatie Laravel Permission

- It is the most used permission package in the Laravel ecosystem, actively maintained, and supports Laravel 13 (v8).
- Its [wildcard permissions](https://spatie.be/docs/laravel-permission/v8/basic-usage/wildcard-permissions) already support `*`, comma subparts and omitted trailing parts, which is exactly the `entities.{action}.{entityId}.{scope}` grammar.
- The wildcard matcher is **case-sensitive** (it indexes the parts as array keys), so mixed-case short ids like `xY1a5G` are safe.
- Roles, direct permissions, guards, the permission cache and the Artisan commands come for free; the future `admin` package can build its UI on them.

Bouncer was considered: it is less active and offers nothing extra for tree-scoped permissions.

## Why a policy plus an opt-in guard

Laravel's place for authorization is a policy: `can()`, `@can`, `can:` middleware and form requests all use it, and it has a user. The package therefore registers `EntityPolicy` for `Entity` (Laravel resolves it for subclasses too).

Model events also fire in seeders, migrations, queues and console commands, where there is no user, so enforcing **only** through events would block legitimate work. The event guard is therefore:

- off by default (`KUSIKUSICMS_ACL_GUARD`);
- active only in HTTP requests;
- bypassable with `Acl::withoutAuthorization()`.

It is a safety net for controllers that forgot to authorize, not the main mechanism.

## How a single check works

`PermissionResolver` turns "may this subject do *action* on *E*?" into a short list of concrete permission names and asks Spatie's `checkPermissionTo()` for each:

```text
entities.{action}.{E}.self
entities.{action}.{parent of E}.children
entities.{action}.{each ancestor of E}.descendants
```

Matching is therefore 100% Spatie's. The ancestors come from one query on `entities_relations`, memoized for the request. The subject is the user **and** the guest role (`Role` has the same `checkPermissionTo()`), so guest permissions apply to everyone.

`create` uses the parent: `children` of the parent, and `descendants` of the parent and its ancestors.

## How list filtering works

SQL cannot call Spatie's matcher, so `whereUserCan()` reads the subject's permission names (user, roles, guest), parses those with the configured prefix into `EntityPermission` value objects, keeps those that allow the action, and folds them into three id sets: self, children and descendants. These become `id IN`, `parent_entity_id IN` and one `EXISTS` on `entities_relations`. Wildcard entity ids become "all rows" (self) or "rows with a parent" (children, descendants).

The tests compare both paths on the same fixtures, so a list never shows an entity that a single check would deny, or the other way round.

## Limits

- **No deny rules.** Permissions only grant; hide a branch by not granting it (see the private branch [recipe](recipes.md)).
- **No teams** and **no per-field permissions** yet.
- **Mass query writes** (`query()->update()`, `upsert()`) skip model events, so the guard cannot see them.
- **Queued jobs** run in the console: authorize when dispatching.
- **One site per database**, like the rest of KusikusiCMS 13.x.
- Entity ids cannot contain `.` or `,` (the permission separators); KusikusiCMS short ids never do.
- Moving an entity changes which `children`/`descendants` permissions apply to it, as soon as `entities_relations` is refreshed after the save.
