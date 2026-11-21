<?php

namespace KusikusiCMS\Api\Http\Requests;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * PUT /entities/{entity}/contents/{lang} with a body of {field: text|null}. Requires `update` on the entity.
 */
class SaveContentsRequest extends EntityRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->entity());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * The body keys are field names, so they are checked here.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (! preg_match(EntityRules::LANG, (string) $this->route('lang'))) {
                $validator->errors()->add('lang', 'The language code is invalid.');
            }

            if ($this->fields() === []) {
                $validator->errors()->add('fields', 'At least one field is required.');
            }

            foreach ($this->fields() as $field => $value) {
                if (! preg_match(EntityRules::FIELD, (string) $field)) {
                    $validator->errors()->add((string) $field, 'The field name is invalid.');
                } elseif ($value !== null && ! is_string($value)) {
                    $validator->errors()->add((string) $field, 'The value must be a string or null.');
                }
            }
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        return $this->json()->all();
    }
}
