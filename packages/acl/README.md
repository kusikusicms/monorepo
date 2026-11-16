# KusikusiCMS ACL (Laravel 13)

> **Read-only split.** This repository is automatically split from [`packages/acl`](https://github.com/kusikusicms/monorepo/tree/main/packages/acl) in the [kusikusicms/monorepo](https://github.com/kusikusicms/monorepo). Please open issues and pull requests in the monorepo; pull requests sent here are closed automatically.
> Install it with `composer require kusikusicms/acl`.

Controls who can **view, create, update and delete** KusikusiCMS entities, including whole branches of the entity tree. It is built on [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/) (v8) with wildcard permissions, so roles, direct permissions, caching and the Spatie commands all work as usual.

```text
entities.{action}.{entityId}.{scope}

entities.view                          view every entity
entities.update.aBc12.self             update the entity aBc12
entities.create.blog.children          create entities directly under blog
entities.*.blog.self,descendants       do anything on the blog branch
```

## Quick start

```bash
composer require kusikusicms/acl
```

Add Spatie's `HasRoles` trait to your user model:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

Run the installer and migrate:

```bash
php artisan k:acl:install   # publishes Spatie's config and migration, enables wildcards, creates the "guest" role
php artisan migrate
php artisan k:acl:install --no-interaction   # once more, so the guest role is created now that the tables exist
```

Then check permissions the Laravel way:

```blade
@can('update', $entity)
    <a href="{{ route('entities.edit', $entity) }}">Edit</a>
@endcan
```

## Features

| Feature | Example | Docs |
|---|---|---|
| Tree-aware permission names | `entities.update.blog.descendants` | [Permissions](docs/permissions.md) |
| A guest role for everyone, signed in or not | `entities.view.*.*` on `guest` | [Roles and guests](docs/roles-and-guests.md) |
| Policy for `can()`, `@can`, `can:` middleware | `$user->can('create', [Entity::class, 'blog'])` | [Enforcement](docs/enforcement.md) |
| Optional guard that blocks unauthorized writes in HTTP requests | `KUSIKUSICMS_ACL_GUARD=true` | [Enforcement](docs/enforcement.md#the-event-guard) |
| Filtered lists in one query | `Entity::query()->whereUserCan('view')->paginate()` | [Querying](docs/querying.md) |
| Artisan commands | `php artisan k:acl:check update post1 --user=1` | [Commands](docs/commands.md) |

A short example of each:

```php
// Build a permission name in PHP and give it to a role (Spatie API)
$name = EntityPermission::make(['create', 'update'], 'blog', ['self', 'descendants'])->name();
Role::findOrCreate('author')->givePermissionTo(Permission::findOrCreate($name));

// Policy: create is checked on the parent
Gate::allows('create', [Entity::class, 'blog']);

// Opt-in guard: throws AuthorizationException (403) on unauthorized writes during HTTP requests
Acl::withoutAuthorization(fn () => $entity->delete()); // explicit bypass

// List only what the current user (or the guest) may see
$entities = Entity::query()->childrenOf('blog')->whereUserCan('view')->withContents()->get();
```

```bash
php artisan k:acl:create-permission --action=update --entity=blog --scope=descendants --role=editor
php artisan k:acl:check update post1 --user=1
```

## Documentation

- [Installation](docs/installation.md): interactive and CI installs, uuid/ulid user ids, existing Spatie apps, several guards.
- [Permissions](docs/permissions.md): the name format, a table of examples over a sample tree, common mistakes.
- [Roles and guests](docs/roles-and-guests.md): the guest role, roles with Spatie commands or seeders, assigning them.
- [Enforcement](docs/enforcement.md): controllers, Blade, middleware, form requests, API resources and the event guard.
- [Querying](docs/querying.md): `whereUserCan()` with the other entity filters and pagination.
- [Commands](docs/commands.md): every `k:acl:*` command and the related Spatie commands.
- [Recipes](docs/recipes.md): public sites, members-only sections, blog authors, editors, private branches, testing.
- [Design decisions](docs/design-decisions.md): why Spatie, why a policy plus a guard, and the limits.

Spatie Laravel Permission documentation: https://spatie.be/docs/laravel-permission/

## License

MIT. See [LICENSE](LICENSE).
