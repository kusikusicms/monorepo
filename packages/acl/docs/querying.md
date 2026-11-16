# Querying

`whereUserCan()` filters an `Entity` query to the entities a subject may act on. It translates the subject's permissions (direct, from roles, and from the guest role) into one grouped `where`, so lists and single checks always agree.

```php
Entity::query()->whereUserCan(string $action = 'view', ?Authenticatable $user = null)
```

- `$action`: `view`, `update` or `delete`. `create` is accepted but applies the same `self`/`children`/`descendants` rules as the other actions, not the [parent rule](permissions.md#create-is-checked-on-the-parent); use `Gate::allows('create', [Entity::class, $parentId])` to know where a user may create.
- `$user`: defaults to `auth()->user()`; without one, only the guest permissions apply.

## The current user, a given user, guests

```php
// Whoever is signed in (or the guest)
$visible = Entity::query()->whereUserCan()->get();

// A given user, for example in a job or a command
$editable = Entity::query()->whereUserCan('update', $user)->get();

// The guest: nobody signed in (for example in a public controller, or in the console)
$public = Entity::query()->whereUserCan('view')->get();
```

## Combined with the other entity filters

It is a normal builder method, so it combines with every `EntityBuilder` filter and with the routes package:

```php
$posts = Entity::query()
    ->childrenOf('blog')
    ->ofModel('Post')
    ->currentlyLive()
    ->whereUserCan('view')
    ->withContents(['lang' => 'en', 'fields' => ['title', 'summary']])
    ->withRoute('en')
    ->orderByContent('title')
    ->get();
```

It never changes the selected columns, so `select()`, `withContents()` and joins keep working.

## Pagination

```php
$page = Entity::query()
    ->descendantsOf('home')
    ->whereUserCan('view')
    ->paginate(20);
```

The filter is part of the `where` clause, so the page count is correct (no post-filtering).

## The generated SQL

The permissions that allow the action are folded into at most three conditions:

```sql
where (
    entities.id in ('home', 'aBc12')                         -- self
    or entities.parent_entity_id in ('blog')                 -- children
    or exists (select 1 from entities_relations as r          -- descendants
               where r.caller_entity_id = entities.id
                 and r.kind = 'ancestor'
                 and r.called_entity_id in ('ln65'))
)
```

- `entities.{action}.*.self` (or `entities.view`) matches every row, so no condition is added.
- `entities.{action}.*.children` and `*.descendants` match every entity with a parent.
- Without any matching permission the query returns nothing (`0 = 1`).

Ids are compared case-sensitively, like the single checks (`BINARY` on MySQL/MariaDB).

## Calling it on other models

The macro is registered globally on Eloquent builders but only works for `Entity` and its subclasses; other models throw a `LogicException`.

IDE autocompletion: `packages/acl/stubs/ide-helper.php` documents the macro.
