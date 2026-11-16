# Permissions

Entity permissions are ordinary [Spatie permissions](https://spatie.be/docs/laravel-permission/) whose **name** follows this format, matched with Spatie's [wildcard permissions](https://spatie.be/docs/laravel-permission/v8/basic-usage/wildcard-permissions):

```text
entities.{action}.{entityId}.{scope}
```

| Part | Values |
|---|---|
| prefix | `entities` (configurable as `kusikusicms.acl.prefix`), so your app can keep other permissions |
| action | `view`, `create`, `update`, `delete` or `*` |
| entityId | an entity id, or `*` for any entity |
| scope | `self` (the entity), `children` (direct children), `descendants` (any depth below, not the entity itself), or `*` |

Every part accepts `*` and several values separated by commas: `entities.create,update.home.self,children`.

Shorter names work as in Spatie: missing parts are wildcards. `entities.view` means `entities.view.*.*`, and `entities` means everything.

## Examples

All the examples use this sample tree (the same tree is used by the package tests, so the table is always true):

```text
home
├── blog
│   ├── post1
│   │   └── comment1
│   └── post2
├── members
│   └── guide
└── aBc12
ln65
└── old1
```

| Permission | Allows |
|---|---|
| `entities.view` | view every entity (and nothing else) |
| `entities.create.blog.children` | create entities whose parent is `blog` |
| `entities.update.aBc12.self` | update `aBc12` only |
| `entities.delete.ln65.children` | delete `old1` (children of `ln65`), not `ln65` itself |
| `entities.create,update.home.self,children` | update `home`, `blog`, `members` and `aBc12`; create direct children of `home` |
| `entities.*.blog.self,descendants` | every action on `blog`, `post1`, `comment1` and `post2`; create anywhere under `blog` |

To check one yourself:

```bash
php artisan k:acl:check update aBc12 --user=1
```

## Create is checked on the parent

A new entity does not exist yet, so `create` is checked against its **parent**:

- `entities.create.P.children` allows creating entities whose parent is `P`;
- `entities.create.A.descendants` allows creating entities anywhere under `A` (parent `A` or any descendant of `A`);
- creating a root entity (no parent) needs a full wildcard: `entities.create` or `entities.create.*.*`.

```php
Gate::allows('create', [Entity::class, 'blog']);   // may I create a child of blog?
Gate::allows('create', Entity::class);             // may I create a root entity?
```

## Common mistakes

- **`entities.create.X.self` grants nothing.** Create is checked on the parent, so use `children` or `descendants`. `k:acl:create-permission` warns about it.
- **Ids are case-sensitive.** `entities.view.xy1a5g.self` does not match the entity `xY1a5G`. The commands reject unknown ids.
- **`descendants` excludes the entity itself.** Use `self,descendants` for a whole branch.
- **Wildcards must be enabled** (`permission.enable_wildcard_permission => true`). Without them the names are compared literally and almost nothing matches. `php artisan about` shows the setting.
- **Entity ids cannot contain dots or commas**, because these are the separators. KusikusiCMS short ids never do.

## Building names in PHP

```php
use KusikusiCMS\Acl\Enums\EntityAction;
use KusikusiCMS\Acl\Enums\EntityScope;
use KusikusiCMS\Acl\Support\EntityPermission;
use Spatie\Permission\Models\Permission;

EntityPermission::make('view')->name();                                    // entities.view.*.*
EntityPermission::make(EntityAction::Update, 'aBc12', EntityScope::Self)->name(); // entities.update.aBc12.self
EntityPermission::make(['create', 'update'], 'home', ['self', 'children'])->name();
// entities.create,update.home.self,children

Permission::findOrCreate(EntityPermission::make('delete', 'ln65', 'children')->name());

EntityPermission::parse('entities.view');   // actions ['view'], entityIds ['*'], scopes ['*']
EntityPermission::parse('other.thing');     // null: not an entity permission
```

Invalid parts (unknown actions or scopes, empty parts, ids with dots or commas) throw `KusikusiCMS\Acl\Exceptions\InvalidEntityPermissionException`.
