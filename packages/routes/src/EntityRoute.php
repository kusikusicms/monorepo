<?php

namespace KusikusiCMS\Routes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Routes\Enums\RouteKind;
use KusikusiCMS\Routes\Exceptions\RoutePathConflictException;
use KusikusiCMS\Routes\Services\RouteManager;

/**
 * A run-time URL path of an Entity.
 *
 * @property int $route_id
 * @property string $entity_id
 * @property string $path
 * @property string $entity_model
 * @property string|null $entity_view
 * @property string $lang
 * @property RouteKind $kind
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Entity|null $entity
 */
class EntityRoute extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'entities_routes';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'route_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'entity_id',
        'path',
        'entity_model',
        'entity_view',
        'lang',
        'kind',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => RouteKind::class,
        ];
    }

    /**
     * The entity this route belongs to.
     *
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id', 'id');
    }

    /**
     * Find the route stored for a path, of any kind. The path is normalised first,
     * so "section/page/" and "/section//page" both match "/section/page".
     * The result exposes the owner entity (entity_id) and the language (lang).
     */
    public static function findByPath(string $path): ?self
    {
        return static::query()->where('path', static::normalizePath($path))->first();
    }

    /**
     * Manually create (or convert) a route of an entity, e.g. an alias or a redirect.
     * The entity's model and view are denormalised. A new primary route demotes the current
     * primary of that entity and language to a permanent redirect. Delegates to RouteManager.
     *
     * @throws RoutePathConflictException When another entity (or another language) uses the path as primary or alias
     */
    public static function createFor(string $entityId, string $lang, string $path, RouteKind $kind = RouteKind::Primary): self
    {
        return app(RouteManager::class)->createRoute($entityId, $lang, $path, $kind);
    }

    /**
     * Normalise a path: one leading slash, no repeated or trailing slashes, no surrounding whitespace.
     * An empty path becomes "/".
     */
    public static function normalizePath(string $path): string
    {
        $segments = array_filter(
            explode('/', trim($path)),
            fn (string $segment) => trim($segment) !== '',
        );

        return '/'.implode('/', array_map('trim', $segments));
    }
}
