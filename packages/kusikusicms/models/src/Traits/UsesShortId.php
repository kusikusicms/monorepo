<?php

namespace KusikusiCMS\Models\Traits;

use Illuminate\Support\Facades\Config;
use PUGX\Shortid\Shortid;

trait UsesShortId
{

    /**
     * The "booting" method of the model, This helps to magically create uuid for all new models
     *
     * @return void
     */
    public static function bootUsesShortId(): void
    {
        self::creating(function ($model) {
            if (!isset($model[$model->getKeyName()])) {
                do {
                    $id = Shortid::generate(Config::get('kusikusicms.models.short_id_length', 10));
                    $found_duplicate = self::where($model->getKeyName(), $id)->first();
                } while (!!$found_duplicate);
                $model->setAttribute($model->getKeyName(), $id);
            } else {
                $model->setAttribute($model->getKeyName(), substr($model[$model->getKeyName()], 0, 16));
            }
        });
    }
    
    public $incrementing = false;
    
    public $keyType = 'string';

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName(): string
    {
        return 'id';
    }
}
