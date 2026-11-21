<?php

namespace KusikusiCMS\Api\Http\Requests;

use Illuminate\Support\Facades\Gate;

/**
 * Update or delete a relation of {entity}. Requires `update` on the calling entity.
 */
class UpdateRelationRequest extends EntityRequest
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
            'position' => ['sometimes', 'nullable', 'integer'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
        ];
    }
}
