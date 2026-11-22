<?php

namespace KusikusiCMS\Acl\Api\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;
use KusikusiCMS\Acl\Abilities;
use KusikusiCMS\Acl\Api\Http\Requests\Concerns\ParsesEntityPermissions;
use KusikusiCMS\Acl\Support\EntityPermission;

/**
 * One `{action, entity_id, scope}` to grant to or revoke from a role.
 */
class RolePermissionRequest extends FormRequest
{
    use ParsesEntityPermissions;

    public function authorize(): bool
    {
        return Gate::allows(Abilities::MANAGE_ROLES);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => $this->permissionPartRules(),
            'entity_id' => $this->permissionPartRules(),
            'scope' => $this->permissionPartRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->parsePermissions($validator, ['' => $this->all()]));
    }

    public function entityPermission(): EntityPermission
    {
        return $this->parsedPermissions()[''];
    }
}
