# Models and Scopes

This package includes the following primary models:
- `KusikusiCMS\Models\Entity` — main content node. Uses short string IDs and soft deletes.
- `KusikusiCMS\Models\EntityContent` — localized field/value rows for an entity.
- `KusikusiCMS\Models\EntityRelation` — graph relations between entities (ancestor/child, etc.).
- `KusikusiCMS\Models\EntityArchive` — stores versions/archives (reserved for future use).

## Entity status accessor
`$entity->status` returns one of: `unknown`, `draft`, `scheduled`, `outdated`, `published` based on `published`, `publish_at`, `unpublish_at`.

Example:
```
$e = Entity::factory()->published()->create();
echo $e->status; // "published"
```

## Content helpers
### `Entity::createContents(array $fields, ?string $lang = null): int`
Upserts multiple fields for the entity in the given (or default) language.

```
$e->createContents(['title' => 'Hello', 'body' => 'World'], 'en');
```

## Scopes (Entity)

### `ofModel(string $model): Builder`
Filter by model ID string.
```
Entity::query()->ofModel('Article')->get();
```

### `withContents(?array $options = null): Builder`
Eager load the `contents` relation optionally filtered by language and field(s).
Options keys: `lang` (string|null) and `fields` (string|array|null).
```
Entity::query()->withContents(['lang' => 'es', 'fields' => ['title', 'summary']])->get();
```

### `orderByContent(string $field, string $order = 'asc', ?string $lang = null): Builder`
Join and order by the content `text` for a field. Uses `config('kusikusicms.models.default_language')` when `$lang` is omitted.
```
Entity::query()->orderByContent('title', 'desc')->get();
```

### `whereContent(string $field, string $param2, ?string $param3 = null, ?string $param4 = null): Builder`
Filter by content using field equality and an operator applied to the `text` column. Two overloads are supported:
- Overload A: `whereContent($field, $value, ?$lang = null)` → compares `field = :field` and `text = :value`.
- Overload B: `whereContent($field, $operator, $value, ?$lang = null)` → compares `field = :field` and `text $operator :value`.

Rules:
- Language resolution: `$lang === null` uses `config('kusikusicms.models.default_language')`; `$lang === ''` searches across any language.
- Operators: `=`, `!=`, `<>`, `like`, `not like`, `ilike`, `rlike` (driver support varies).
- LIKE convenience: if you use `like`/`not like` without `%`, the value is wrapped as `%value%` automatically.

Examples:
```
// Equals (default operator)
Entity::query()->whereContent('title', 'Hello')->get();

// LIKE (starts with)
Entity::query()->whereContent('title', 'like', 'Hello%')->get();

// Any language
Entity::query()->whereContent('title', 'like', 'Hola%', '')->get();
```

## Relation graph scopes

### `childrenOf(string $entityId, ?string $tag = null): Builder`
Direct children (depth=1) of a given parent. Current code exposes dot-style SQL aliases in attributes (e.g., `child.position`).
```
Entity::query()->childrenOf($parentId)->get();
```

### `parentOf(string $entityId): Builder`
Direct parent of a given entity. Exposes `parent.*` dot-style aliases.
```
Entity::query()->parentOf($childId)->first();
```

### `ancestorsOf(string $entityId): Builder`
All ancestors (any depth). Exposes `ancestor.*` dot-style aliases.
```
Entity::query()->ancestorsOf($id)->get();
```

> Upcoming change: We plan to switch to snake_case aliases (e.g., `parent_position`) and include `entities.*` in selects for consistent hydration. That change will be documented as a minor breaking change with migration guidance.

## Collections helpers
- `EntityCollection::flattenContentsByField()` → transforms the `contents` relation of each entity into `['field' => 'text']`.
- `EntityCollection::groupContentsByField()` → `['field' => ['lang' => 'text']]`.
- `EntityCollection::groupContentsByLang()` → `['lang' => ['field' => 'text']]`.

See also: `EntityContentsCollection` specialized reducers.
