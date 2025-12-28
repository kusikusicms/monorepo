# KusikusiCMS Models (Laravel 12)

This package provides the core Eloquent models and helpers for KusikusiCMS on Laravel 12, including:
- `Entity` with short string IDs, lifecycle events, scopes, and content helpers
- `EntityContent`, `EntityRelation`, and `EntityArchive`
- Collections helpers for transforming related contents
- Factory states for realistic testing/seed data

Version: 12.0.0-alpha.1

## Installation

1) Require the package (when published):

```
composer require kusikusicms/models
```

2) Configuration is auto-merged. To publish the config file:

```
php artisan vendor:publish --tag=kusikusicms-config
```

This will create `config/kusikusicms/models.php`.

## Configuration

All keys live under the `kusikusicms.models.*` namespace:
- `short_id_length` (int, default 10) — length of generated IDs
- `short_id_max_attempts` (int, default 5) — retries before throwing on collision
- `default_language` (string, default `en`) — used by content helpers/scopes
- `store_versions` (bool, default true) — used by archives (reserved for future use)

See docs/configuration.md for details.

## Migrations

The service provider loads the package migrations automatically. They use timezone-aware timestamps (`timestampsTz`, `softDeletesTz`) and FK helpers.

Run migrations as usual:
```
php artisan migrate
```

## Quick start

### Create an entity (short ID assigned automatically)
```
use KusikusiCMS\Models\Entity;

$entity = Entity::create(['model' => 'Article']);
```

### Attach contents
```
$entity->createContent([
  'title' => 'Hello',
  'body'  => 'World',
]); // defaults to config('kusikusicms.models.default_language')
```

### Query with content-aware scopes
- Load related contents with optional filters:
```
Entity::query()->withContents('es', ['title'])->get();
```
- Order by a content field (uses configured default language if omitted):
```
Entity::query()->orderByContent('title', 'desc')->get();
```
- Filter by a content field:
```
// Current behavior is characterized in tests; future version will compare `field =` and apply operator to `text` only.
Entity::query()->whereContent('title', 'like', 'Hello%')->get();
```

### Relations graph scopes
```
Entity::query()->childrenOf($parentId)->get();
Entity::query()->parentOf($childId)->first();
Entity::query()->ancestorsOf($entityId)->get();
```

### Factory states
```
Entity::factory()->draft()->create();
Entity::factory()->scheduled()->create();
Entity::factory()->published()->create();
Entity::factory()->outdated()->create();

// With contents helper
Entity::factory()->published()->withContents(['title' => 'Hello'])->create();
```

### Events

`Entity` dispatches custom events via `$dispatchesEvents` so host apps can listen to them. Example:
```
use Illuminate\Support\Facades\Event;
use KusikusiCMS\Models\Events\EntityCreated;

Event::listen(EntityCreated::class, fn ($event) => logger('Entity created', ['id' => $event->entity->id]));
```

See docs/events.md for the full list.

### Collections helpers

When loading `contents`, use collection helpers to transform related rows into convenient arrays:
```
$entities = Entity::withContents('en')->get();
$entities->flattenContentsByField();      // ['title' => 'Hello', 'body' => 'World']
$entities->groupContentsByField();        // ['title' => ['en' => 'Hello'], ...]
$entities->groupContentsByLang();         // ['en' => ['title' => 'Hello'], ...]
```

## Testing

- Package tests (Orchestra Testbench):
```
cd packages/kusikusicms/models
../../../vendor/bin/phpunit -c phpunit.xml
```

## Extensibility

### Replace the ID generator
Bind your own generator to `KusikusiCMS\Models\Support\IdGenerator` in the host app container to control short ID generation.

See docs/id-generator.md for an example.

## Documentation
- docs/installation.md
- docs/configuration.md
- docs/models-and-scopes.md
- docs/events.md
- docs/factories-and-seeding.md
- docs/testing.md
- docs/id-generator.md
- docs/design-decisions.md

## License
MIT