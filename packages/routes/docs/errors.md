# Errors

The package throws standard exceptions and lets the host application's exception handler render them.

## HTTP errors

| Case | Exception | Status |
|---|---|---|
| Unknown path | `abort(404)` | 404 |
| Format not in `formats` | `abort(404)` | 404 |
| Redirect route without a primary route | `abort(404)` | 404 |
| Entity trashed, unpublished, scheduled or expired | `abort(404)` | 404 |
| No method for the model and the default `fallback()` | `abort(404)` | 404 |
| `findOrFail()` in your controller | `ModelNotFoundException` | 404 |
| Missing view, bugs | Any exception | 500 |
| `controller` does not extend `EntityRouteController` | `LogicException` | 500 |

Customise the pages as in any Laravel app, for example with `resources/views/errors/404.blade.php`.

## Path conflicts

`KusikusiCMS\Routes\Exceptions\RoutePathConflictException` is a domain exception (a `RuntimeException`, not an HTTP one). It is thrown when a write would reuse a path owned by another entity, or by the same entity in another language:
- saving a slug content (including `createContents()`);
- moving an entity to another parent;
- `EntityRoute::createFor()` / `RouteManager::createRoute()`.

It exposes:

| Property | Description |
|---|---|
| `path` | The conflicting path |
| `conflictingEntityId` | The entity that owns it |
| `lang` | The language of the owner's route |

The write is rolled back: the slug content or the parent change is not saved and no route rows change.

Map it to a validation error where you save contents, for example:

```PHP
use Illuminate\Validation\ValidationException;
use KusikusiCMS\Routes\Exceptions\RoutePathConflictException;

try {
    $entity->createContents($request->validated('contents'), $lang);
} catch (RoutePathConflictException $e) {
    throw ValidationException::withMessages([
        'contents.slug' => "The path {$e->path} is already used by {$e->conflictingEntityId}.",
    ]);
}
```

## Invalid slugs

A slug containing `/` (after trimming leading and trailing slashes and whitespace) throws `InvalidArgumentException`, and the content is not saved.
