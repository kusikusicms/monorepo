# AGENTS.md — `packages/acl`

Guidance for coding agents working on `kusikusicms/acl` (`KusikusiCMS\Acl`). Read the root `AGENTS.md` first; this file adds the package-specific rules. Human docs live in `README.md` and `docs/*.md`.

## Purpose and boundaries

- Decides who may **view, create, update and delete** entities, using `spatie/laravel-permission` v8 with wildcard permissions.
- In scope: permission grammar, resolver, `EntityPolicy`, the opt-in event guard, the `whereUserCan()` macro, the `k:acl:*` commands, and managing roles, role permissions and user roles (domain actions plus API endpoints) behind the manage permission.
- Out of scope: the admin UI (future SPA), user create/edit/delete, direct user permissions (roles only), delegated administration (granting only what you hold), an audit trail, teams, per-field permissions, deny rules.
- No role commands: in the CLI roles are managed with Spatie's `permission:create-role` / `permission:assign-role`. `k:acl:create-permission --role=` only creates a missing role to attach a permission, and `k:acl:install --admin-role=` only seeds the manage permission and an admin role.
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
| `Services\PermissionResolver` | single checks: builds candidate names from the tree, asks Spatie `checkPermissionTo()` on the user and the guest role; `allows`, `allowsCreate`, `matchingPermission`, `matchingCreatePermission`, `hasAnyPermission`, `guestRole`, `clearCache`; `primeAncestors(ids)` loads many ancestor lists in one query; `batch(fn)` remembers a missing guest role only while the batch runs |
| `Api\AclApiContribution` | registered from the provider only when `KusikusiCMS\Api\Api` exists, its provider is loaded and `kusikusicms.acl.api.enabled`: `can` flags hook (primes ancestors and runs one `batch()` per response), index modifier `whereUserCan('view')`, `routes/api.php`, the `acl` capability, and handlers for `KusikusiCMS\Routes\Abilities` (mapped to `update`) when routes is installed |
| `Api\Http\Controllers\MePermissionsController` | `GET /me/permissions`: user and guest entity permissions parsed with `EntityPermission`, plus `can_manage` |
| `Abilities` | Gate ability constants `VIEW_ROLES`, `MANAGE_ROLES`, `ASSIGN_ROLES`, `SEARCH_USERS`, `VIEW_ENTITY_ACCESS` (`kusikusicms.acl.*`); defined by the provider with `Gate::define` (outside the api check) and all resolve to `Acl::canManage()` |
| `Actions\{CreateRole,RenameRole,DeleteRole,SyncRolePermissions,GrantRolePermission,RevokeRolePermission,SyncUserRoles}` | role and user-role writes, usable without api; share `Actions\Concerns\WritesAcl` (transaction, last-manager check, guest-role protection, cache clearing) |
| `Services\ManagerGuard` | `countManagers()` (users holding the manage permission or `*`, via roles or directly, in the acl guard, one union query) and `ensureManagerRemains()` (throws `LastManagerException`) |
| `Services\EntityAccessInspector` | `forEntity(Entity)`: each role's `{view, create, update, delete}` on one entity and the permissions granting them; `candidates()` mirrors `PermissionResolver::candidatesFor()` and `matchingCreatePermission()` |
| `Support\RolePermissions` | groups permission names into `entity` (parsed), `manage` and `other`; `same()` compares permissions regardless of part order |
| `Exceptions\LastManagerException`, `ProtectedRoleException` | mapped by `AclApiContribution` to 409 `last_manager` / `protected_role` |
| `Api\Http\Controllers\{Roles,RolePermissions,UserSearch,UserRoles,EntityAccess}Controller` | thin controllers for `/roles`, `/roles/{id}/permissions`, `/users`, `/users/{id}/roles`, `/entities/{id}/permissions`; Form Requests authorize with the `Abilities` (403 before validation) and parse permissions with `Requests\Concerns\ParsesEntityPermissions` (one `whereIn` for entity ids) |
| `Support\PermissionParser` | list filtering: reads the user's and guest's permission names and folds them into `all`, `non_root`, `self_ids`, `children_ids`, `descendant_ids` |
| `Builders\EntityAclQueries` + `EntityAclMacros` | the `whereUserCan()` mixin on `EntityBuilder`; public methods return closures (each public method becomes a macro, keep helpers elsewhere, e.g. `Support\CaseSensitiveWhereIn`) |
| `Policies\EntityPolicy` | Laravel abilities, nullable user (works for guests) |
| `Listeners\AuthorizeEntityWrites` | the event guard |
| `Acl` | `withoutAuthorization()` bypass (nesting-safe, restored on exceptions); `managePermission()`, `canManage($user)`, `userModel()` (from the acl guard's user provider), `usersApiEnabled()` |
| `Console\InstallCommand`, `CreatePermissionCommand`, `CheckCommand` | `kusikusicms:acl:*` with `k:acl:*` aliases; every prompt has an option fallback (`--admin-role=` seeds the manage permission and an admin role) |

## Invariants

- **The parser and the Spatie matcher must agree.** Any grammar change goes to `EntityPermission`, `PermissionResolver::candidatesFor()` and `PermissionParser`/`EntityAclQueries` together; `DocumentationExamplesTest::test_lists_and_single_checks_agree_for_every_documented_permission` and `EntityAclQueriesTest` must stay green.
- **Create is checked on the parent**, everywhere (resolver, policy, guard, check command).
- **Guest = everyone**: every check ORs the user with the guest role. Never memoize a missing guest role (a role created later in the process must be picked up), except inside `PermissionResolver::batch()`, which forgets it when the batch ends.
- **api is optional**: `kusikusicms/api` is only in `suggest`. Every reference to `KusikusiCMS\Api\*` or `KusikusiCMS\Routes\*` lives in `src/Api/` and runs only behind the `class_exists` checks.
- **The access view and the policy must agree.** `EntityAccessInspector` duplicates the candidate logic; change it with `PermissionResolver` and keep `EntityAccessInspectorTest::test_the_access_view_agrees_with_the_policy` green.
- **Managing access needs the manage permission**, reads included (`kusikusicms.acl.manage_permission`, which must not start with the entity prefix). Override a single ability with `Gate::define`, never by editing the handlers.
- **Never lock out the last manager**: role and user-role writes that take the manager count from at least one to zero throw `LastManagerException` and roll back. Deleting a role, removing `manage` and syncing a user's roles use the check; with no manager yet nothing is refused.
- **The guest role** cannot be renamed, deleted or assigned to users (`ProtectedRoleException`, 422 in requests); its permissions can be edited.
- **Every access write clears both caches** (Spatie's `forgetCachedPermissions()` and `PermissionResolver::clearCache()`) after the transaction. Sync only replaces entity permissions (and `manage` when given); user-role sync keeps roles of other guards.
- **The user search exposes only `id`, `name`, `email` and role names.** Its routes and the `users` capability exist only when `api.users.enabled` and the user model uses `HasRoles`.
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

API contribution tests live in `tests/Api` (`ApiTestCase` loads Sanctum and the api provider and flushes the api hooks in `tearDown`; fixture `ApiUser` with `HasRoles` and `HasApiTokens`). Domain tests for access management (`ManagePermissionTest`, `RoleActionsTest`, `EntityAccessInspectorTest`) live in `tests/Feature` and run without api.

## Quality gate

Before a change is done: `composer test`, `composer lint -- --test`, `composer audit` and `composer analyse -- --no-progress` (do not grow `phpstan-baseline.neon`).

## Commits

Conventional Commits with the `acl` scope, e.g. `feat(acl): add deny rules`, `fix(acl): ...`, `docs(acl): ...`.
