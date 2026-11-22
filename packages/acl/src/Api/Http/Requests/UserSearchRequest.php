<?php

namespace KusikusiCMS\Acl\Api\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Acl\Abilities;

class UserSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(Abilities::SEARCH_USERS);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
