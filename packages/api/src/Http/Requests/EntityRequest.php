<?php

namespace KusikusiCMS\Api\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use KusikusiCMS\Models\Entity;

/**
 * Base request for routes with an {entity} parameter. The entity is loaded once (404 when missing).
 */
abstract class EntityRequest extends FormRequest
{
    private ?Entity $resolvedEntity = null;

    /**
     * Whether soft deleted entities can be resolved by this request.
     */
    protected bool $withTrashed = false;

    public function entity(): Entity
    {
        if ($this->resolvedEntity === null) {
            $query = Entity::query();

            if ($this->withTrashed) {
                $query->withTrashed();
            }

            $this->resolvedEntity = $query->findOrFail((string) $this->route('entity'));
        }

        return $this->resolvedEntity;
    }
}
