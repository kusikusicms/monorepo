# Commands

The ACL commands build and check **entity permissions**. Roles are managed with Spatie's own commands (see [below](#related-spatie-commands)), so there is only one way to do each thing. Every command has a short `k:` alias.

## `kusikusicms:acl:install` (`k:acl:install`)

Configures Spatie and the ACL. See [installation](installation.md) for every step.

```bash
php artisan k:acl:install
```

```text
 ┌ Which auth guard do the permissions use? ────────────────────┐
 │ web                                                          │
 └──────────────────────────────────────────────────────────────┘
 ┌ Which type are the ids of your users? ───────────────────────┐
 │ › ● Integer (auto increment)                                 │
 │   ○ UUID                                                     │
 │   ○ ULID                                                     │
 └──────────────────────────────────────────────────────────────┘
 ┌ Create a guest role whose permissions apply to everyone (visitors and users)? ┐
 │ ● Yes / ○ No                                                 │
 └──────────────────────────────────────────────────────────────┘
 ...
   INFO  Created: config/permission.php.
   INFO  Enabled wildcard permissions in config/permission.php.
   INFO  Created: database/migrations/2026_11_14_101500_create_permission_tables.php.
   INFO  Created: config/kusikusicms/acl.php.
   INFO  Role [guest] has: entities.view.*.*
```

Non-interactive:

```bash
php artisan k:acl:install --guard=web --user-id-type=uuid --guest-role=guest \
    --guest-permissions="entities.view.*.*" --event-guard=no --no-interaction
php artisan k:acl:install --guest-role=none --no-interaction   # no guest role
php artisan k:acl:install --force --event-guard=yes --no-interaction   # rewrite config/kusikusicms/acl.php
```

## `kusikusicms:acl:create-permission` (`k:acl:create-permission`)

Builds a valid permission name, creates it (`Permission::findOrCreate`) and optionally attaches it to a role (created when missing).

| Option | Meaning |
|---|---|
| `--action=*` | `view`, `create`, `update`, `delete` or `*`; repeat it or separate with commas |
| `--entity=*` | entity ids or `*` (default); unknown ids are rejected, they are case-sensitive |
| `--scope=*` | `self`, `children`, `descendants` or `*` (default) |
| `--role=` | attach the permission to this role |
| `--guard=` | the guard (default `kusikusicms.acl.guard_name`) |

```bash
php artisan k:acl:create-permission
```

```text
 ┌ Which actions does the permission allow? ────────────────────┐
 │   ◻ All actions                                              │
 │ › ◼ view                                                     │
 │   ◻ create                                                   │
 │   ◼ update                                                   │
 │   ◻ delete                                                   │
 └──────────────────────────────────────────────────────────────┘
 ┌ Which entity ids? (separated by commas, * for all) ──────────┐
 │ blog                                                         │
 └──────────────────────────────────────────────────────────────┘
 ┌ Which entities, relative to the entity ids? ─────────────────┐
 │ ◼ self   ◼ descendants                                       │
 └──────────────────────────────────────────────────────────────┘
 ┌ Attach it to a role? (empty for none) ───────────────────────┐
 │ author                                                       │
 └──────────────────────────────────────────────────────────────┘
   INFO  Permission [entities.view,update.blog.self,descendants] is ready for the [web] guard.
   INFO  Attached to the role [author].
```

Non-interactive:

```bash
php artisan k:acl:create-permission --action=view --role=guest --no-interaction
# entities.view.*.*
php artisan k:acl:create-permission --action=create,update --entity=home --scope=self,children --role=editor --no-interaction
# entities.create,update.home.self,children
php artisan k:acl:create-permission --action=create --entity=home --scope=self --no-interaction
#   WARN  "create" with only the "self" scope grants nothing: create is checked on the parent, use "children" or "descendants".
```

## `kusikusicms:acl:check` (`k:acl:check`)

Tells whether a user, or the guest, may do an action on an entity, and which permission matched. For `create`, the entity is the **parent**. The exit code is `0` when allowed and `1` when denied, so it can be used in scripts.

```bash
php artisan k:acl:check update post1 --user=1
#   INFO  Allowed: User [1] can update [post1] (matched entities.update.blog.descendants).

php artisan k:acl:check view members
#   ERROR  Denied: The guest cannot view [members].

php artisan k:acl:check create blog --user=1
#   INFO  Allowed: User [1] can create children of [blog] (matched entities.create.blog.children).
```

The user is loaded from `auth.providers.users.model`. The command warns when wildcard permissions are disabled.

## Related Spatie commands

| Command | Use |
|---|---|
| `php artisan permission:create-role editor web "entities.view\|entities.update"` | create a role with permissions |
| `php artisan permission:assign-role editor 1` | give a role to the user with id 1 |
| `php artisan permission:create-permission "entities.view" web` | create a permission by name |
| `php artisan permission:show` | table of roles and permissions per guard |
| `php artisan permission:cache-reset` | clear the permission cache |

See the [Spatie docs](https://spatie.be/docs/laravel-permission/v8/basic-usage/artisan) for their options.
