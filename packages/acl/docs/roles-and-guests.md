# Roles and guests

The package does not impose roles. Roles and their assignment are plain [Spatie roles](https://spatie.be/docs/laravel-permission/v8/basic-usage/role-permissions); this page shows the usual setups.

## The guest role

The role named in `kusikusicms.acl.guest_role` (default `guest`) applies to **everyone**:

- unauthenticated visitors get its permissions;
- every authenticated user gets its permissions **in addition** to their own.

So one permission makes a public website:

```bash
php artisan k:acl:create-permission --action=view --role=guest --no-interaction   # entities.view.*.*
```

You never assign the guest role to users. If the role does not exist, guests get nothing.

### Disabling it

```php
// config/kusikusicms/acl.php
'guest_role' => null,
```

Or install with `php artisan k:acl:install --guest-role=none`.

## Creating roles with commands

Spatie's commands manage roles; the ACL commands build entity permissions:

```bash
# An editor that can view and update everything
php artisan permission:create-role editor web "entities.view|entities.update"

# An author that manages the blog branch (the role is created when missing)
php artisan k:acl:create-permission --action='*' --entity=blog --scope=descendants --role=author --no-interaction
php artisan k:acl:create-permission --action=view,update --entity=blog --scope=self --role=author --no-interaction

# An admin that can do anything with entities
php artisan permission:create-role admin web "entities"

php artisan permission:show
```

## Creating roles in a seeder

```php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use KusikusiCMS\Acl\Support\EntityPermission;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $grant = fn (string $role, array $names) => Role::findOrCreate($role)
            ->givePermissionTo(array_map(fn ($name) => Permission::findOrCreate($name), $names));

        $grant('guest', [EntityPermission::make('view')->name()]);
        $grant('editor', ['entities.view,update.*.*']);
        $grant('author', ['entities.view,update.blog.self', 'entities.*.blog.descendants']);
        $grant('admin', ['entities']);
    }
}
```

Seeders run in the console, so the [event guard](enforcement.md#the-event-guard) never blocks them.

## Assigning roles and direct permissions

```bash
php artisan permission:assign-role editor 1
```

```php
$user->assignRole('editor');
$user->givePermissionTo(Permission::findOrCreate('entities.update.aBc12.self')); // a direct permission
$user->removeRole('editor');
$user->syncRoles(['author']);
```

Users get the union of their direct permissions, their roles' permissions and the guest permissions. Spatie flushes its permission cache when roles or permissions change; run `php artisan permission:cache-reset` after editing the tables by hand.
