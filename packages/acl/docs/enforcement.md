# Enforcement

The package registers `KusikusiCMS\Acl\Policies\EntityPolicy` for `Entity` (and its subclasses), so Laravel's authorization works with entities out of the box. An optional [event guard](#the-event-guard) can also block unauthorized writes.

| Ability | Permission action | Notes |
|---|---|---|
| `viewAny` | `view` | true when the subject has any view permission |
| `view` | `view` | |
| `create` | `create` | checked on the parent: `can('create', [Entity::class, $parentId])` |
| `update` | `update` | |
| `delete` | `delete` | |
| `restore`, `forceDelete` | `delete` | |

Every ability also works for guests (no user), using the [guest role](roles-and-guests.md).

## Controllers

```php
use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Models\Entity;

class EntityController extends Controller
{
    public function update(Request $request, Entity $entity)
    {
        Gate::authorize('update', $entity); // 403 when denied
        $entity->update($request->validated());
    }

    public function store(Request $request)
    {
        Gate::authorize('create', [Entity::class, $request->input('parent_entity_id')]);
        // ...
    }

    public function show(Entity $entity)
    {
        if (Gate::denies('view', $entity)) {
            abort(404); // hide its existence instead of 403
        }
    }
}
```

`$user->can('update', $entity)`, `Gate::forUser($user)->allows(...)` and `$this->authorize(...)` (with the `AuthorizesRequests` trait) work the same.

## Blade

```blade
@can('update', $entity)
    <a href="{{ route('entities.edit', $entity) }}">Edit</a>
@endcan

@cannot('delete', $entity)
    <p>You cannot delete this page.</p>
@endcannot

@can('create', [\KusikusiCMS\Models\Entity::class, $entity->id])
    <a href="{{ route('entities.create', ['parent' => $entity->id]) }}">Add a child</a>
@endcan
```

## Route middleware

```php
Route::put('/entities/{entity}', [EntityController::class, 'update'])->can('update', 'entity');
Route::delete('/entities/{entity}', [EntityController::class, 'destroy'])->middleware('can:delete,entity');
Route::get('/entities', [EntityController::class, 'index'])->can('viewAny', Entity::class);
```

## Form requests

```php
class StoreEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gate uses the current user, or the guest when nobody is signed in
        return Gate::allows('create', [Entity::class, $this->input('parent_entity_id')]);
    }
}

class UpdateEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('entity'));
    }
}
```

## API resources

```php
class EntityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'model' => $this->model,
            'can' => [
                'update' => Gate::allows('update', $this->resource),
                'delete' => Gate::allows('delete', $this->resource),
                'create' => Gate::allows('create', [Entity::class, $this->id]),
            ],
        ];
    }
}

// Only list what the user may see, see querying.md
return EntityResource::collection(Entity::query()->whereUserCan('view')->paginate());
```

## The event guard

The policy only runs where you call it. The event guard is a safety net that blocks unauthorized writes even if a controller forgot to authorize. It is **off by default**:

```dotenv
KUSIKUSICMS_ACL_GUARD=true
```

When enabled, listeners check the current user (`auth()->user()`, or the guest) and throw `Illuminate\Auth\Access\AuthorizationException` (a 403 response) on:

| Event | Required |
|---|---|
| `EntityCreating` | `create` under the new entity's parent |
| `EntityUpdating` | `update` on the entity; when `parent_entity_id` changes, also `create` under the new parent |
| `EntityDeleting` | `delete` |
| `EntityRestoring` | `delete` |
| `EntityContentSaving`, `EntityContentDeleting` | `update` on the content's entity |

The exception is thrown before the write, so nothing is saved; `Entity::createContents()` runs in a transaction and rolls back as a whole.

### HTTP only

The guard only runs in HTTP requests. Console commands, seeders, migrations, tinker and queued jobs (which run in the console) are never blocked, because they have no user. Authorize queued work when you dispatch it.

### Bypassing it

```php
use KusikusiCMS\Acl\Acl;

Acl::withoutAuthorization(function () use ($entity) {
    $entity->update(['view' => 'archived']); // a system write during a request
});
```

The bypass is restored even when the callback throws, and calls can be nested.

### Handling the 403

Laravel turns the exception into a 403 response. To customize it:

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (AuthorizationException $e, Request $request) {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'You cannot change this entity.'], 403);
        }
    });
})
```

Mass query writes (`Entity::query()->update()`, `upsert()`) do not fire model events, so the guard does not see them. Authorize those explicitly.
