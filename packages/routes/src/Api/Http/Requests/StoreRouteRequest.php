<?php

namespace KusikusiCMS\Routes\Api\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Routes\Abilities;
use KusikusiCMS\Routes\Enums\RouteKind;

/**
 * Create an alias or a redirect for {entity}. Primary routes come from slugs and are never created here.
 */
class StoreRouteRequest extends FormRequest
{
    private ?Entity $resolvedEntity = null;

    public function authorize(): bool
    {
        return Gate::allows(Abilities::CREATE_ROUTE, $this->entity());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:2048', 'not_regex:/[?#]/'],
            'lang' => ['sometimes', 'string', 'max:6'],
            'kind' => ['sometimes', Rule::in([
                RouteKind::Alias->value,
                RouteKind::PermanentRedirect->value,
                RouteKind::TemporaryRedirect->value,
            ])],
        ];
    }

    public function entity(): Entity
    {
        return $this->resolvedEntity ??= Entity::query()->findOrFail((string) $this->route('entity'));
    }
}
