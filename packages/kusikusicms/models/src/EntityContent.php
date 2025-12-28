<?php

namespace KusikusiCMS\Models;

use Illuminate\Database\Eloquent\Model;
use KusikusiCMS\Models\Support\EntityContentsCollection;

class EntityContent extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'entities_contents';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'entity_id',
        'lang',
        'field',
        'text',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * To avoid "ambiguous" SQL errors Change the primary key for the model.
     *
     * @return string
     */
    public function getKeyName()
    {
        return 'content_id';
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param  array<int, EntityContent>  $contents
     * @return EntityContentsCollection<int, Entity>
     */
    public function newCollection(array $contents = []): EntityContentsCollection
    {
        return new EntityContentsCollection($contents);
    }
}
