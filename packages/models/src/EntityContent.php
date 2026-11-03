<?php

namespace KusikusiCMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use KusikusiCMS\Models\Events\EntityContentCreated;
use KusikusiCMS\Models\Events\EntityContentCreating;
use KusikusiCMS\Models\Events\EntityContentDeleted;
use KusikusiCMS\Models\Events\EntityContentDeleting;
use KusikusiCMS\Models\Events\EntityContentSaved;
use KusikusiCMS\Models\Events\EntityContentSaving;
use KusikusiCMS\Models\Events\EntityContentUpdated;
use KusikusiCMS\Models\Events\EntityContentUpdating;
use KusikusiCMS\Models\Support\EntityContentsCollection;

/**
 * @property int $content_id
 * @property string $entity_id
 * @property string $lang
 * @property string $field
 * @property string|null $text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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
     * The event map for the model, so other packages (e.g. routes) can react to content changes.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'creating' => EntityContentCreating::class,
        'created' => EntityContentCreated::class,
        'updating' => EntityContentUpdating::class,
        'updated' => EntityContentUpdated::class,
        'saving' => EntityContentSaving::class,
        'saved' => EntityContentSaved::class,
        'deleting' => EntityContentDeleting::class,
        'deleted' => EntityContentDeleted::class,
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
     * @param  array<int, EntityContent>  $models
     * @return EntityContentsCollection<int, Entity>
     */
    public function newCollection(array $models = []): EntityContentsCollection
    {
        return new EntityContentsCollection($models);
    }
}
