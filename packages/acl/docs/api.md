# JSON API contribution

When [`kusikusicms/api`](../../api/README.md) is installed, acl adds to it (disable with `KUSIKUSICMS_ACL_API=false` / `kusikusicms.acl.api.enabled`):

- **`can` flags** on every entity resource: `{view, update, delete, create_child}`, answered by `EntityPolicy` (`create_child` is `create` with the entity as parent). The UI should never decide permissions itself.
- **Filtered lists**: every entity list is restricted with `whereUserCan('view')`, so users only see what they may view.
- **`GET /api/v1/me/permissions`**: the entity permissions of the signed-in user and of the guest role, parsed, and whether the user may manage access:

```json
{"data": {
  "user": [{"name": "entities.update.blog.descendants", "actions": ["update"], "entity_ids": ["blog"], "scopes": ["descendants"]}],
  "guest": [{"name": "entities.view.*.*", "actions": ["view"], "entity_ids": ["*"], "scopes": ["*"]}],
  "can_manage": false
}}
```

- **Route abilities**: when `kusikusicms/routes` is installed, `kusikusicms.routes.create` and `kusikusicms.routes.delete` are allowed when the user may `update` the entity.
- **Roles, role permissions, user roles and the per-entity access view**, see [Managing access](#managing-access).
- The capability `acl` with the features `can-flags`, `filtered-lists`, `me.permissions`, `roles`, `permissions.manage`, `entity-access`, and `users` when the user search is enabled. The SPA hides the access screens when they are missing or when `can_manage` is false.

## Managing access

These endpoints let an admin SPA manage access without Spatie's `permission:*` commands. All of them require authentication and the **manage permission**; anyone else gets 403 `forbidden`, reads included.

### The manage permission

A plain Spatie permission, `kusikusicms.acl.manage` by default (`kusikusicms.acl.manage_permission`; it must not start with the entity prefix). Seed it with the install command:

```bash
php artisan k:acl:install --admin-role=admin
```

This creates the permission and an `admin` role holding it (interactively, the command asks). Then assign the role: `$user->assignRole('admin')`. With wildcard permissions on, a role holding `*` is a manager too (super admin).

The checks are Gate abilities in `KusikusiCMS\Acl\Abilities` (`VIEW_ROLES`, `MANAGE_ROLES`, `ASSIGN_ROLES`, `SEARCH_USERS`, `VIEW_ENTITY_ACCESS`). They are defined even without the API and all resolve to `Acl::canManage($user)`, so an app can override one, for example to let editors read the access view:

```php
Gate::define(Abilities::VIEW_ENTITY_ACCESS, fn ($user) => $user->hasRole('editor') || Acl::canManage($user));
```

### Endpoints

All paths are under `/api/v1`.

| Method | Path | Behaviour |
|---|---|---|
| GET | `/roles` | Paginated `{id, name, permissions_count, users_count}`, `?search=` |
| POST | `/roles` | `{name}`, unique in the guard. 201 |
| GET | `/roles/{id}` | The role with its grouped permissions |
| PATCH | `/roles/{id}` | `{name}` |
| DELETE | `/roles/{id}` | 204 |
| GET | `/roles/{id}/permissions` | `{entity: [...], manage: bool, other: [names]}` |
| PUT | `/roles/{id}/permissions` | `{permissions: [{action, entity_id, scope}], manage?: bool}` replaces the entity permissions |
| POST | `/roles/{id}/permissions` | Grants one `{action, entity_id, scope}`. 201 |
| DELETE | `/roles/{id}/permissions` | Revokes one `{action, entity_id, scope}`. 204 |
| GET | `/users` | `?search=` over the search columns, paginated `{id, name, email, roles[]}` |
| GET | `/users/{id}/roles` | Role names |
| PUT | `/users/{id}/roles` | `{roles: [names]}` replaces the user's roles |
| GET | `/entities/{id}/permissions` | Each role's access to the entity |

Roles are those of `kusikusicms.acl.guard_name`. Roles of other guards are not listed, and they are kept when a user's roles are replaced.

### Permission payloads

Clients never build permission names. `action`, `entity_id` and `scope` each take a string, a list or `*`:

```json
{"permissions": [
  {"action": ["create", "update"], "entity_id": "blog", "scope": ["self", "descendants"]},
  {"action": "view", "entity_id": "*", "scope": "*"}
], "manage": false}
```

Invalid actions or scopes, a `.` or `,` inside a part, and unknown entity ids return 422 `validation_failed`. Entity permissions are returned as `{name, action[], entity_id[], scope[]}`.

`PUT` replaces only the entity permissions: other permissions of the role are kept, and `manage` changes only when it is sent. Grant is idempotent. Revoke matches the same parts in any order and never deletes the permission row.

### Rules

- **Guest role**: it cannot be renamed or deleted (409 `protected_role`) and cannot be assigned to users (422). Its permissions can be edited.
- **Last manager**: a change that would leave no user holding the manage permission (through a role or directly) returns 409 `last_manager` and changes nothing. This covers deleting a role, removing `manage` from a role and replacing a user's roles. When there is no manager yet, nothing is refused.
- Every write clears Spatie's permission cache and the `PermissionResolver` cache, so the policy sees the change at once.

The same rules apply in PHP through the actions in `KusikusiCMS\Acl\Actions` (`CreateRole`, `RenameRole`, `DeleteRole`, `SyncRolePermissions`, `GrantRolePermission`, `RevokeRolePermission`, `SyncUserRoles`), which work without the API.

### User search

`GET /users` returns only `id`, `name`, `email` and role names, never passwords or tokens. Configure it in `kusikusicms.acl.api.users`:

```php
'users' => [
    'enabled' => env('KUSIKUSICMS_ACL_API_USERS', true),
    'search_columns' => ['name', 'email'],
],
```

The user model is resolved from the acl guard's user provider. The `/users` routes and the `users` capability feature exist only when `enabled` is true and the model uses Spatie's `HasRoles`; otherwise the routes return 404.

### Entity access view

`GET /entities/{id}/permissions` lists, for every role, which actions it has on the entity and through which permissions:

```json
{"data": [
  {"role": "editor", "actions": {"view": false, "create": true, "update": true, "delete": false},
   "via": [{"name": "entities.create,update.blog.descendants", "action": ["create", "update"], "entity_id": ["blog"], "scope": ["descendants"]}]}
]}
```

As in the policy, `create` means creating a child of the entity. `EntityAccessInspector` computes the answer from the same candidates as `PermissionResolver`, and a test checks that it agrees with `EntityPolicy`. The guest role is listed like any other role.

## Performance

The role and user lists and the access view run a fixed number of queries (the access view runs three, whatever the number of roles).

The flags of a whole page are computed at once: `PermissionResolver::primeAncestors()` loads the ancestors of every entity of the page in one query and `PermissionResolver::batch()` looks a missing guest role up only once, so the number of queries does not depend on the page size.

```php
$resolver = app(PermissionResolver::class);
$resolver->primeAncestors($entities->pluck('id'));
$resolver->batch(fn () => $entities->map(fn ($e) => Gate::allows('update', $e)));
```
