<?php

namespace KusikusiCMS\Api\Http\Requests;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;
use KusikusiCMS\Models\Entity;

/**
 * Partial update. Requires `update` on the entity and, when it is moved, `create` on the new parent.
 */
class UpdateEntityRequest extends EntityRequest
{
    public function authorize(): bool
    {
        $entity = $this->entity();

        if (! Gate::allows('update', $entity)) {
            return false;
        }

        if ($this->has('parent_entity_id') && $this->input('parent_entity_id') !== $entity->parent_entity_id) {
            $parentId = $this->input('parent_entity_id');
            $parent = is_string($parentId) ? Entity::query()->find($parentId) : null;

            return Gate::allows('create', [Entity::class, $parent ?? $parentId]);
        }

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge([
            'model' => ['sometimes', 'string', 'max:32'],
        ], EntityRules::attributes(), EntityRules::contents());
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => EntityRules::validateContentKeys($validator, $this->input('contents')),
            function (Validator $validator) {
                $parentId = $this->input('parent_entity_id');

                if (! is_string($parentId)) {
                    return;
                }

                $entity = $this->entity();
                $isOwnDescendant = $parentId === $entity->id
                    || Entity::query()->descendantsOf($entity->id)->where('entities.id', $parentId)->exists();

                if ($isOwnDescendant) {
                    $validator->errors()->add('parent_entity_id', 'An entity cannot be moved under itself or its descendants.');
                }
            },
        ];
    }
}
