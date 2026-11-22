<?php

namespace KusikusiCMS\Acl\Api\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;
use KusikusiCMS\Acl\Abilities;
use KusikusiCMS\Acl\Api\Http\Requests\Concerns\ParsesEntityPermissions;
use KusikusiCMS\Acl\Support\EntityPermission;

/**
 * `{permissions: [{action, entity_id, scope}], manage?: bool}` replacing the entity permissions of a role.
 */
class SyncRolePermissionsRequest extends FormRequest
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
            'permissions' => ['present', 'array', 'list'],
            'permissions.*' => ['array'],
            'permissions.*.action' => $this->permissionPartRules(),
            'permissions.*.entity_id' => $this->permissionPartRules(),
            'permissions.*.scope' => $this->permissionPartRules(),
            'manage' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $payloads = [];

            foreach ((array) $this->input('permissions', []) as $index => $payload) {
                $payloads['permissions.'.$index] = (array) $payload;
            }

            $this->parsePermissions($validator, $payloads);
        });
    }

    /**
     * @return list<EntityPermission>
     */
    public function entityPermissions(): array
    {
        return array_values($this->parsedPermissions());
    }

    public function manage(): ?bool
    {
        return $this->has('manage') ? $this->boolean('manage') : null;
    }
}
