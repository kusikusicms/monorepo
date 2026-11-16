# AGENTS.md — `packages/acl`

Guidance for coding agents working on `kusikusicms/acl` (`KusikusiCMS\Acl`). Read the root `AGENTS.md` first; this file adds the package-specific rules. Human docs live in `README.md` and `docs/*.md`.

## Purpose and boundaries

- Decides who may **view, create, update and delete** entities, using `spatie/laravel-permission` v8 with wildcard permissions.
- In scope: permission grammar, resolver, `EntityPolicy`, the opt-in event guard, the `whereUserCan()` macro, the `k:acl:*` commands.
- Out of scope: role management UI and user management (future `admin` package), teams, per-field permissions, deny rules.
- No role commands: roles are managed with Spatie's `permission:create-role` / `permission:assign-role`. `k:acl:create-permission --role=` only creates a missing role to attach a permission.
- Depends on `kusikusicms/models`; never change the models public API from here (use events, macros and `Gate::policy`).

## Permission grammar

```text
{prefix}.{action}.{entityId}.{scope}      prefix = config('kusikusicms.acl.prefix'), default "entities"
action   view | create | update | delete | *
entityId entity id | *                    (case-sensitive, no "." or ",")
scope    self | children | descendants | *  (descendants = depth >= 1, never the entity itself)
```

- Every part accepts `*` and comma subparts; missing trailing parts are wildcards (Spatie semantics): `entities.view` = `entities.view.*.*`.
- `create` is checked on the **parent**: `create.P.children`, or `create.A.descendants` for `A` in `{P} ∪ ancestors(P)`. A root entity needs `create.*.*`. `create.X.self` grants nothing.
- Policy `restore`/`forceDelete` use `delete`; `viewAny` is true with any view permission; `EntityContent` writes need `update` on the entity.

## Class map

| Class | Role |
|---|---|
| `AclServiceProvider` | merges `kusikusicms.acl`, resolver singleton, `Gate::policy`, macros, guard listeners (only when `guard.enabled`), `EntitySaved` cache flush, commands, `AboutCommand` |
| `Enums\EntityAction`, `Enums\EntityScope` | the action and scope values |
| `Support\EntityPermission` | immutable value object: `make()`, `parse()`, `name()`, `allows()`; throws `InvalidEntityPermissionException` |
| `Services\PermissionResolver` | single checks: builds candidate names from the tree, asks Spatie `checkPermissionTo()` on the user and the guest role; `allows`, `allowsCreate`, `matchingPermission`, `matchingCreatePermission`, `hasAnyPermission`, `guestRole`, `clearCache` |
| `Support\PermissionParser` | list filtering: reads the user's and guest's permission names and folds them into `all`, `non_root`, `self_ids`, `children_ids`, `descendant_ids` |
| `Builders\EntityAclQueries` + `EntityAclMacros` | the `whereUserCan()` mixin on `EntityBuilder`; public methods return closures (each public method becomes a macro, keep helpers elsewhere, e.g. `Support\CaseSensitiveWhereIn`) |
| `Policies\EntityPolicy` | Laravel abilities, nullable user (works for guests) |
| `Listeners\AuthorizeEntityWrites` | the event guard |
| `Acl` | `withoutAuthorization()` bypass (nesting-safe, restored on exceptions) |
| `Console\InstallCommand`, `CreatePermissionCommand`, `CheckCommand` | `kusikusicms:acl:*` with `k:acl:*` aliases; every prompt has an option fallback |

## Invariants

- **The parser and the Spatie matcher must agree.** Any grammar change goes to `EntityPermission`, `PermissionResolver::candidatesFor()` and `PermissionParser`/`EntityAclQueries` together; `DocumentationExamplesTest::test_lists_and_single_checks_agree_for_every_documented_permission` and `EntityAclQueriesTest` must stay green.
- **Create is checked on the parent**, everywhere (resolver, policy, guard, check command).
- **Guest = everyone**: every check ORs the user with the guest role. Never memoize a missing guest role (a role created later in the process must be picked up).
- **The guard is HTTP-only and opt-in**: `config('kusikusicms.acl.guard.enabled')`, `! app()->runningInConsole()` and `! Acl::isAuthorizationBypassed()`.
- **Never use mass query writes** (`query()->update()`, `upsert()`) in package code for entities or contents: they skip the events the guard relies on.
- **`whereUserCan()` never changes the selected columns** and adds one grouped `where`; it throws `LogicException` on non-Entity builders.
- **Ids are case-sensitive** in SQL too (`BINARY` on MySQL/MariaDB).
- Keep `stubs/ide-helper.php` (not autoloaded, outside PHPStan paths), `README.md`, `docs/*.md` and `DocumentationExamplesTest` in sync with behavior changes.
- `InstallCommand::config()` rewrites `config/acl.php` with regexes: keep the `'guard_name' =>`, `'guest_role' =>` and `'enabled' => env('KUSIKUSICMS_ACL_GUARD', ...)` lines (the command throws if they no longer match).

## Event-order caveats

- `EntityCreating`: the entity has no `entities_relations` rows yet; the guard checks `create` on `parent_entity_id` (the parent's ancestors exist).
- `EntityUpdating`: `update` is checked on a clone with the **original** `parent_entity_id`; a parent change also needs `create` under the new parent.
- `restore()` fires `EntityRestoring` and then an `EntityUpdating` for `deleted_at`; the guard marks the entity on restoring (a `WeakMap`) and skips that internal update, so restore needs only `delete`.
- The custom `EntitySaved` fires before `EntityObserver::saved` refreshes `entities_relations`; the resolver adds `parent_entity_id` to the ancestors itself and flushes its caches on `EntitySaved`.

## Tests

```bash
vendor/bin/phpunit --testsuite acl                      # from the monorepo root
vendor/bin/phpunit --testsuite acl --filter Resolver
```

Orchestra Testbench, SQLite in memory, Spatie migrations loaded from the vendor stub (`tests/Fixtures/Migrations`), wildcards on, fixture `User` with `HasRoles` (`guard_name = web`) and `UserWithoutRoles`. Simulate HTTP for the guard by setting `Application::$isRunningInConsole` to false (see `AuthorizeEntityWritesTest`). Install tests write into the Testbench skeleton and clean up in `setUp`/`tearDown`.

## Quality gate

Before a change is done: `composer test`, `composer lint -- --test`, `composer audit` and `composer analyse -- --no-progress` (do not grow `phpstan-baseline.neon`).

## Commits

Conventional Commits with the `acl` scope, e.g. `feat(acl): add deny rules`, `fix(acl): ...`, `docs(acl): ...`.
