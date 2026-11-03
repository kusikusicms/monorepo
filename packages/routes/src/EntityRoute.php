<?php

namespace KusikusiCMS\Routes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Routes\Enums\RouteKind;

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
