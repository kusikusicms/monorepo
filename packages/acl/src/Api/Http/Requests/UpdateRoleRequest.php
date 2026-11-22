<?php

namespace KusikusiCMS\Acl\Api\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use KusikusiCMS\Acl\Abilities;

class UpdateRoleRequest extends FormRequest
{
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
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique((string) config('permission.table_names.roles', 'roles'), 'name')
                    ->where('guard_name', (string) config('kusikusicms.acl.guard_name', 'web'))
                    ->ignore($this->route('role')),
            ],
        ];
    }
}
