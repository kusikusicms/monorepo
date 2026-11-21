<?php

namespace KusikusiCMS\Api\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SessionLoginRequest extends FormRequest
{
    /**
     * Anyone may attempt to log in; the throttle protects the endpoint.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }
}
