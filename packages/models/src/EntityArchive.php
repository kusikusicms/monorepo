<?php

namespace KusikusiCMS\Models;

use Illuminate\Database\Eloquent\Model;

class EntityArchive extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'entities_archives';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'entity_id',
        'kind',
        'version',
        'payload',
    ];
}
