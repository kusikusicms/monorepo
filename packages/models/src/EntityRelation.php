<?php

namespace KusikusiCMS\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Pivot;

class EntityRelation extends Pivot
{
    const RELATION_ANCESTOR = 'ancestor';

    const RELATION_UNDEFINED = 'relation';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'entities_relations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable
        = [
            'caller_entity_id',
            'called_entity_id',
            'kind',
            'position',
            'depth',
            'tags',
        ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden
        = [
            'created_at',
            'updated_at',
            'caller_entity_id',
            'called_entity_id',
        ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts
        = [
            'tags' => 'array',
        ];

    protected $guarded = ['relation_id'];

    /**
     * To avoid "ambiguous" errors Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName()
    {
        return 'relation_id';
    }

    /**
     * The relation to the Entity is calling
     */
    public function caller_entity()
    {
        return $this->belongsTo('KusikusiCMS\Models\Entity', 'caller_entity_id',
            'relation_id');
    }

    /**
     * The relation to the Entity is being called
     */
    public function called_entity()
    {
        return $this->belongsTo('KusikusiCMS\Models\Entity', 'called_entity_id',
            'relation_id');
    }

    /***
     * SCOPES
     */

    /**
     * Filter scope. Get the relations that match the filter criteria
     *
     * @param  array  $filter  An array with optional caller_entity_id, called_entity_id, kind, position, depth, tag
     */
    public function scopeFilter(Builder $query, array $filter): Builder
    {
        if (isset($filter['caller_entity_id'])) {
            $query->where('caller_entity_id', $filter['caller_entity_id']);
        }
        if (isset($filter['called_entity_id'])) {
            $query->where('called_entity_id', $filter['called_entity_id']);
        }
        if (isset($filter['kind'])) {
            $query->where('kind', $filter['kind']);
        }
        if (isset($filter['position'])) {
            $query->where('position', $filter['position']);
        }
        if (isset($filter['depth'])) {
            $query->where('depth', $filter['depth']);
        }
        if (isset($filter['tag'])) {
            $query->whereJsonContains('tags', $filter['tag']);
        }

        return $query;
    }
}
