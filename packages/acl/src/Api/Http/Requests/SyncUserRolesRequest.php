<?php

namespace KusikusiCMS\Acl\Api\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use KusikusiCMS\Acl\Abilities;

/**
 * `{roles: [names]}`: existing roles of the acl guard, never the guest role.
 */
class SyncUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(Abilities::ASSIGN_ROLES);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'roles' => ['present', 'array', 'list'],
            'roles.*' => [
                'string',
                'distinct',
                Rule::notIn([(string) config('kusikusicms.acl.guest_role')]),
                Rule::exists((string) config('permission.table_names.roles', 'roles'), 'name')
                    ->where('guard_name', (string) config('kusikusicms.acl.guard_name', 'web')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.*.not_in' => 'The guest role applies to everyone and cannot be assigned to users.',
        ];
    }

    /**
     * @return list<string>
     */
    public function roleNames(): array
    {
        return array_values(array_map('strval', (array) $this->validated('roles')));
    }
}
