# Installation

The ACL package builds on [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/) v8. The installer configures Spatie for you, but everything it does can also be done by hand.

## 1. Require the package

```bash
composer require kusikusicms/acl
```

This also installs `spatie/laravel-permission`. Both service providers are auto-discovered.

## 2. Add `HasRoles` to your user model

```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

Users whose model lacks the trait still work, but only get the [guest permissions](roles-and-guests.md). The installer warns when the trait is missing.

## 3. Run the installer

### Interactively

```bash
php artisan k:acl:install
```

It asks:

| Question | Default | Option |
|---|---|---|
| Which auth guard do the permissions use? | `web` | `--guard=web` |
| Which type are the ids of your users? | `int` | `--user-id-type=int\|uuid\|ulid` |
| Create a guest role whose permissions apply to everyone? | yes | `--guest-role=guest` (`none` disables it) |
| Which permissions does the guest role have? | `entities.view.*.*` | `--guest-permissions="entities.view.*.*"` (separated by `\|`) |
| Enable the event guard? | no | `--event-guard=yes\|no` |

It then:

1. publishes `config/permission.php` when missing, and sets `'enable_wildcard_permission' => true` (the ACL needs it);
2. publishes `database/migrations/*_create_permission_tables.php` when missing, and changes the user id columns to `uuid`/`ulid` if you chose so;
3. writes `config/kusikusicms/acl.php` with your answers (skipped when it exists, unless `--force`);
4. creates the guest role and its permissions when the permission tables exist;
5. warns if your user model lacks `HasRoles`.

### Non-interactively (CI, scripts)

Every question has an option, and `--no-interaction` uses the defaults for the rest:

```bash
php artisan k:acl:install --no-interaction
php artisan k:acl:install --guard=web --user-id-type=int --guest-role=guest \
    --guest-permissions="entities.view.*.*" --event-guard=no --no-interaction
```

The command is idempotent: running it again keeps the existing files, never duplicates the migration, and only adds missing permissions to the guest role.

## 4. Migrate

```bash
php artisan migrate
```

On a fresh app the permission tables did not exist during the first install, so run the installer once more to create the guest role:

```bash
php artisan k:acl:install --no-interaction
```

## UUID or ULID user ids

Spatie stores the user id in `model_has_roles` and `model_has_permissions` (`model_id`, see Spatie's [UUID guide](https://spatie.be/docs/laravel-permission/v8/advanced-usage/uuid)). Choose the type before migrating:

```bash
php artisan k:acl:install --user-id-type=uuid --no-interaction
```

This only concerns **user** ids. Entity ids are part of the permission *names* (`entities.view.xY1a5G.self`), so they never need custom Spatie models.

If the migration has already run, create a new migration that changes the `model_id` columns instead.

## Publishing the config

```bash
php artisan vendor:publish --tag=kusikusicms-acl-config
```

`config/kusikusicms/acl.php`:

```php
return [
    'prefix' => 'entities',           // first part of every entity permission
    'guard_name' => 'web',            // guard of the permissions and roles checked
    'guest_role' => 'guest',          // role applied to everyone; null disables it
    'guard' => [
        'enabled' => env('KUSIKUSICMS_ACL_GUARD', false), // the event guard, see enforcement.md
    ],
];
```

`php artisan about` shows whether wildcards, the guest role and the event guard are enabled.

## An app that already uses Spatie

Nothing is lost: the installer keeps your `config/permission.php` and migration, and only turns wildcards on.

```bash
php artisan k:acl:install --guest-role=none --no-interaction
```

Check two things before enabling wildcards in an existing app:

- Existing permission names containing dots now follow the wildcard rules (`posts.edit` also matches a check for `posts.edit.1`). See [Spatie's wildcard docs](https://spatie.be/docs/laravel-permission/v8/basic-usage/wildcard-permissions).
- Your existing roles keep working. Give them entity permissions with `k:acl:create-permission --role=...`.

## Several guards (`web` + `api`)

Spatie permissions and roles belong to a guard. The ACL checks the guard configured in `kusikusicms.acl.guard_name`, so create the entity permissions for that guard:

```bash
php artisan k:acl:create-permission --action=view --guard=web --role=guest --no-interaction
```

If your API users authenticate with another guard (for example Sanctum's `api`) but should share the same permissions, keep `guard_name` at `web` and set `protected $guard_name = 'web';` on the user model, or set the config per request:

```php
// app/Http/Middleware/UseApiAcl.php
config(['kusikusicms.acl.guard_name' => 'api']);
```
