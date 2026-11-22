<?php

namespace KusikusiCMS\Api\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Models\Entity;

/**
 * List parameters shared by /entities and the tree lists. Tree lists authorize `view` on the scoped entity.
 */
class EntityIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entityId = $this->route('entity');

        if ($entityId === null) {
            return Gate::allows('viewAny', Entity::class);
        }

        return Gate::allows('view', Entity::query()->findOrFail((string) $entityId));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $id = ['sometimes', 'string', 'max:26'];

        return [
            'model' => ['sometimes', 'string', 'max:255'],
            'child_of' => $id,
            'descendant_of' => $id,
            'ancestor_of' => $id,
            'sibling_of' => $id,
            'related_by' => $id,
            'relating' => $id,
            'depth' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'kind' => ['sometimes', 'string', 'max:255'],
            'tag' => ['sometimes', 'string', 'max:255'],
            'live' => ['sometimes', 'boolean'],
            'with_contents' => ['sometimes', 'boolean'],
            'lang' => ['sometimes', 'string', 'max:6'],
            'fields' => ['sometimes', 'string', 'max:1000'],
            'sort' => ['sometimes', 'string', 'max:255', 'regex:/^-?(id|model|view|created_at|updated_at|publish_at|unpublish_at|content:[A-Za-z0-9_\-]{1,32})(,-?(id|model|view|created_at|updated_at|publish_at|unpublish_at|content:[A-Za-z0-9_\-]{1,32}))*$/'],
            'trashed' => ['sometimes', 'in:with,only'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'include' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
