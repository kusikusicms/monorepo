<?php

namespace KusikusiCMS\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use KusikusiCMS\Models\Events\EntityCreated;
use KusikusiCMS\Models\Events\EntityCreating;
use KusikusiCMS\Models\Events\EntityDeleted;
use KusikusiCMS\Models\Events\EntityDeleting;
use KusikusiCMS\Models\Events\EntityForceDeleted;
use KusikusiCMS\Models\Events\EntityForceDeleting;
use KusikusiCMS\Models\Events\EntityReplicating;
use KusikusiCMS\Models\Events\EntityRestored;
use KusikusiCMS\Models\Events\EntityRestoring;
use KusikusiCMS\Models\Events\EntityRetrieved;
use KusikusiCMS\Models\Events\EntitySaved;
use KusikusiCMS\Models\Events\EntitySaving;
use KusikusiCMS\Models\Events\EntityTrashed;
use KusikusiCMS\Models\Events\EntityUpdated;
use KusikusiCMS\Models\Events\EntityUpdating;
use KusikusiCMS\Models\Factories\EntityFactory;
use KusikusiCMS\Models\Support\EntityCollection;
use KusikusiCMS\Models\Support\EntityContentsCollection;
use KusikusiCMS\Models\Traits\UsesShortId;

class Entity extends Model
{
    use HasFactory, SoftDeletes, UsesShortId;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'entities';

    /**
     * Create a new factory instance for the Entity model.
     */
    protected static function newFactory(): Factory
    {
        return EntityFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable
        = [
            'id',
            'model',
            'view',
            'langs',
            'props',
            'published',
            'parent_entity_id',
            'created_at',
            'publish_at',
            'unpublish_at',
        ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts
        = [
            'props' => 'array',
            'publish_at' => 'datetime',
            'unpublish_at' => 'datetime',
            'langs' => 'array',
            'published' => 'boolean',
        ];

    /**
     * isPublished attribute
     * @return Attribute
     */
    protected function status(): Attribute
    {
        return new Attribute(
            get: function (mixed $value, array $attributes) {
                    $now = Carbon::now();
                    if (!isset($attributes['published'])) {
                        return 'unknown';
                    } else if (!$attributes['published']) {
                        return 'draft';
                    } else if (!$attributes['publish_at'] || $attributes['publish_at'] > $now) {
                        return 'scheduled';
                    } else if (isset($attributes['unpublish_at']) && $attributes['unpublish_at'] < $now) {
                        return 'outdated';
                    } else {
                        return 'published';
                    }
                },
        );
    }


    /**
     * The event map for the model. Some events are not used here, but they are defined so other packages can use them
     *
     * @var array
     */
    protected $dispatchesEvents
        = [
            'retrieved' => EntityRetrieved::class,
            'creating' => EntityCreating::class,
            'created' => EntityCreated::class,
            'updating' => EntityUpdating::class,
            'updated' => EntityUpdated::class,
            'saving' => EntitySaving::class,
            'saved' => EntitySaved::class,
            'deleting' => EntityDeleting::class,
            'deleted' => EntityDeleted::class,
            'trashed' => EntityTrashed::class,
            'forceDeleting' => EntityForceDeleting::class,
            'forceDeleted' => EntityForceDeleted::class,
            'restoring' => EntityRestoring::class,
            'restored' => EntityRestored::class,
            'replicating' => EntityReplicating::class,
        ];

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param  array<int, Entity>  $models
     *
     * @return EntityCollection<int, Entity>
     */
    public function newCollection(array $models = []): EntityCollection
    {
        return new EntityCollection($models);
    }

    /**
     * Static function to refresh the relations of ANCESTOR kind for the given Entity ID.
     * It also recreates children ANCESTOR relations recursively.
     */
    public static function refreshAncestorsRelationsById(string $entity_id): void
    {
        $entity = Entity::find($entity_id);
        if ($entity) {
            self::refreshAncestorRelationsOfEntity($entity);
        }
    }

    /**
     * Static function to refresh the relations of ANCESTOR kind for the given Entity.
     * It also recreates children's ANCESTOR relations recursively.
     */
    public static function refreshAncestorRelationsOfEntity(Entity $entity): void
    {
        // First clear all ancestor relations of the entity
        EntityRelation::query()
            ->where('caller_entity_id', $entity->id)
            ->where('kind', EntityRelation::RELATION_ANCESTOR)
            ->delete();
        // Now recreate all ancestor relations
        $currentAncestor = Entity::find($entity->parent_entity_id);
        $currentDepth = 1;
        while ($currentAncestor) {
            EntityRelation::create([
                'caller_entity_id' => $entity->id,
                'called_entity_id' => $currentAncestor->id,
                'kind' => EntityRelation::RELATION_ANCESTOR,
                'depth' => $currentDepth,
            ]);
            $currentAncestor = Entity::find($currentAncestor->parent_entity_id);
            $currentDepth++;
        }
        // Descendants should be updated as well
        $children = Entity::where('parent_entity_id', $entity->id)->get();
        foreach ($children as $child) {
            self::refreshAncestorRelationsOfEntity($child);
        }
    }

    /** Refresh the relations of ANCESTOR kind of the Entity
     */
    public function refreshAncestorsRelations(): void
    {
        self::refreshAncestorRelationsOfEntity($this);
    }

    /**********
     * SCOPES *
     **********/

    /**
     * Scope a query to only include entities of a given modelId.
     *
     * @param  string  $model  The id of the model
     */
    public function scopeOfModel(Builder $query, string $model): Builder
    {
        // TODO: Accept array of model ids
        return $query->where('model', $model);
    }

    /**
     * Scope a query to only include children of a given parent id.
     *
     * @param  string  $entity_id  The id of the parent entity
     * @param  string|null  $tag  Filter by one tag
     */
    public function scopeChildrenOf(Builder $query, string $entity_id, ?string $tag = null): Builder
    {
        return $query->join('entities_relations as child',
            function ($join) use ($entity_id, $tag) {
                $join->on('child.caller_entity_id', '=', 'entities.id')
                    ->where('child.called_entity_id', '=', $entity_id)
                    ->where('child.depth', '=', 1)
                    ->where('child.kind', '=',
                        EntityRelation::RELATION_ANCESTOR)
                    ->when($tag, function ($q) use ($tag) {
                        return $q->whereJsonContains('child.tags', $tag);
                    });
            })
            ->addSelect('id')
            ->addSelect('child.position as child.position')
            ->addSelect('child.tags as child.tags');
    }

    /**
     * Scope a query to only include the parent of the given id.
     *
     * @param  string  $entity_id  The id of the parent entity
     */
    public function scopeParentOf(Builder $query, string $entity_id): Builder
    {
        return $query->join('entities_relations as parent', function ($join) use ($entity_id) {
            $join->on('parent.called_entity_id', '=', 'entities.id')
                ->where('parent.caller_entity_id', '=', $entity_id)
                ->where('parent.depth', '=', 1)
                ->where('parent.kind', '=', EntityRelation::RELATION_ANCESTOR);
        })
            ->addSelect('id')
            ->addSelect('parent.relation_id as parent.relation_id')
            ->addSelect('parent.position as parent.position')
            ->addSelect('parent.depth as parent.depth')
            ->addSelect('parent.tags as parent.tags');
    }

    /**
     * Scope a query to only include the ancestors of a given entity.
     * Returns the anscestors in order
     *
     * @param  number  $entity_id  The id of the entity
     * @return Builder
     *
     * @throws \Exception
     */
    public function scopeAncestorsOf(Builder $query, $entity_id): Builder
    {
        return $query->join('entities_relations as ancestor', function ($join) use ($entity_id) {
            $join->on('ancestor.called_entity_id', '=', 'entities.id')
                ->where('ancestor.caller_entity_id', '=', $entity_id)
                ->where('ancestor.kind', '=', EntityRelation::RELATION_ANCESTOR);
        })
            ->addSelect('id')
            ->addSelect('ancestor.relation_id as ancestor.relation_id')
            ->addSelect('ancestor.position as ancestor.position')
            ->addSelect('ancestor.depth as ancestor.depth')
            ->addSelect('ancestor.tags as ancestor.tags');
    }

    /**
     * Scope to include contents relation, filtered by lang and fields.
     *
     * @param  Builder  $query
     * @param  string|null  $lang
     * @param  array|null  $fields
     *
     * @return Builder
     */
    public function scopeWithContents($query, string $lang = null, array $fields = null, ): Builder
    {
        return $query->with(['contents' => function($q) use ($lang, $fields) {
            $q->when($lang !== null, function ($q) use ($lang, $fields) {
                return $q->where('lang', $lang);
            });
            $q->when($fields !== null, function ($q) use ($fields) {
                if (is_array($fields)) return $q->whereIn('field', $fields);
                if (is_string($fields)) return $q->where('field', $fields);
            });
        }]);
    }

    /**
     * Scope to order the result by a content field.
     *
     * @param  Builder  $query
     * @param  string  $field
     * @param  string  $order
     * @param  string|null  $lang
     *
     * @return Builder
     */
    public function scopeOrderByContent($query, string $field, string $order = 'asc', string $lang = null)
    {
        if (!$lang) {
            $lang = Config::get('kusikusicms.models.default_language', 'en');
        }
        return $query->leftJoin("entities_contents as content_order_{$lang}_{$field}", function ($join) use ($field, $lang, $order) {
            $join->on("content_order_{$lang}_{$field}.entity_id", "entities.id")
                ->where("content_order_{$lang}_{$field}.field", $field)
                ->where("content_order_{$lang}_{$field}.lang", $lang)
            ;
        })
            ->orderBy("content_order_{$lang}_{$field}.text", $order);
    }

    /**
     * Scope to filter by a content field.
     *
     * @param  Builder  $query
     * @param  string  $field
     * @param  string  $param2
     * @param  string|null  $param3
     * @param  string|null  $param4
     *
     * @return Builder
     */
    public function scopeWhereContent(Builder $query, string $field, string $param2, string $param3 = null, string $param4 = null)
    {
        if (Str::contains($param2, ['=', 'like'])) {
            $operator = $param2;
            $value = $param3;
            $lang = $param4;
        } else {
            $operator = '=';
            $value = $param2;
            $lang = $param3;
        }
        if (!$lang) {
            $lang = '';
        }
        $value = $operator === 'like' ? "%$value%" : $value;
        return $query->leftJoin("entities_contents as content_where_{$lang}_{$field}", function ($join) use ($field, $operator, $lang, $value) {
            $join->on("content_where_{$lang}_{$field}.entity_id", "entities.id")
                ->where("content_where_{$lang}_{$field}.field", $operator, $field)
                ->when($lang !== '', function ($q) use ($lang, $field) {
                    return $q->where("content_where_{$lang}_{$field}.lang", $lang);
                })
            ;
        })
            ->where("content_where_{$lang}_{$field}.text", $operator, $value);
    }

    /****************
     * Relationships
     ***************/

    /**
     * The generic relations relationship
     */
    public function relations(): HasMany
    {
        return $this
            ->hasMany(EntityRelation::class, 'caller_entity_id', 'id');
    }

    /**
     * The contents relationship
     */
    public function contents(): HasMany
    {
        return $this
            ->hasMany(EntityContent::class, 'entity_id', 'id');
    }

    /****************
     * Methods
     ***************/

    /**
     * Create contents for the current Entity.
     *
     * @param  array  $fieldsAndValues  An associative array of fields and their value
     * @param  string|null  $language The id of the language of the contents
     *
     * @throws \Exception
     */
    public function createContent(array $fieldsAndValues, string $language = null): int
    {
        return EntityContent::upsert(Arr::map($fieldsAndValues, function (string $value, string $key) use ($language) {
            return [
                'entity_id' => $this->id,
                'field' => $key,
                'text' => $value,
                'lang' => $language ?? Config::get('kusikusicms-models.default_language', 'en')
            ];
        }), uniqueBy: ['entity_id', 'field', 'lang'], update: ['text']);
    }

    public function flattenContentsByField(): Entity
    {
        if (isset($this->contents) && $this->contents instanceof EntityContentsCollection) {
            $this->contents = $this->contents->flattenByField();
            $this->setRelation('contents', $this->contents);
        }
        return $this;
    }
    public function groupContentsByField(): Entity
    {
        if (isset($this->contents) && $this->contents instanceof EntityContentsCollection) {
            $this->contents = $this->contents->groupByField();
            $this->setRelation('contents', $this->contents);
        }
        return $this;
    }

    public function groupContentsByLang(): Entity
    {
        if (isset($this->contents) && $this->contents instanceof EntityContentsCollection) {
            $this->contents = $this->contents->groupByLang();
            $this->setRelation('contents', $this->contents);
        }
        return $this;
    }
    /**
     * Get a specific key of the entity props field, using dot notation
     *
     * @param  string  $key  The key of the prop
     * @param  mixed  $default  The default value if the prop is not set
     *
     * @return mixed
     */
    public function getProp(string $key, mixed $default = null): mixed
    {
        $key = Str::replace('->', '.', $key);
        return Arr::get($this->props, $key, $default);
    }
    /**
     * Set a specific key of the entity props field, using dot notation
     *
     * @param  string  $key  The key of the prop
     * @param  mixed  $value  The value of the prop
     *
     * @return Entity
     */
    public function setProp(string $key, mixed $value): Entity
    {
        $key = Str::replace('->', '.', $key);
        $props = $this->props;
        Arr::set($props, $key, $value);
        $this->props = $props;
        return $this;
    }

}
