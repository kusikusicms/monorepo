<?php

namespace KusikusiCMS\Api\Http\Requests;

use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Models\EntityRelation;

/**
 * Create a relation from {entity} to another entity. Requires `update` on the calling entity.
 */
class StoreRelationRequest extends EntityRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->entity());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'called_entity_id' => ['required', 'string', 'exists:entities,id'],
            'kind' => ['required', 'string', 'max:24', 'regex:/^[A-Za-z0-9_\-]+$/', 'not_in:'.EntityRelation::RELATION_ANCESTOR],
            'position' => ['sometimes', 'nullable', 'integer'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
        ];
    }
}
