<?php

namespace KusikusiCMS\Api\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;
use KusikusiCMS\Models\Entity;

/**
 * Create an entity. `create` is authorized on the parent (null for a root entity).
 */
class StoreEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $parentId = $this->input('parent_entity_id');
        $parent = is_string($parentId) ? Entity::query()->find($parentId) : null;

        return Gate::allows('create', [Entity::class, $parent ?? (is_string($parentId) ? $parentId : null)]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge([
            'id' => ['sometimes', 'string', 'max:26', 'regex:/^[A-Za-z0-9_\-]+$/', 'unique:entities,id'],
            'model' => ['required', 'string', 'max:32'],
        ], EntityRules::attributes(), EntityRules::contents());
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => EntityRules::validateContentKeys($validator, $this->input('contents'))];
    }
}
