<?php

namespace KusikusiCMS\Api\Http\Requests;

use Illuminate\Validation\Validator;

/**
 * Validation rules shared by the entity write requests.
 */
final class EntityRules
{
    public const LANG = '/^[A-Za-z]{2,3}([_\-][A-Za-z0-9]{2,3})?$/';

    public const FIELD = '/^[A-Za-z0-9_\-]{1,32}$/';

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function attributes(): array
    {
        return [
            'view' => ['sometimes', 'nullable', 'string', 'max:32'],
            'parent_entity_id' => ['sometimes', 'nullable', 'string', 'exists:entities,id'],
            'props' => ['sometimes', 'nullable', 'array'],
            'published' => ['sometimes', 'boolean'],
            'publish_at' => ['sometimes', 'nullable', 'date'],
            'unpublish_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:publish_at'],
            'langs' => ['sometimes', 'nullable', 'array'],
            'langs.*' => ['string', 'regex:'.self::LANG],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function contents(): array
    {
        return [
            'contents' => ['sometimes', 'array'],
            'contents.*' => ['array'],
            'contents.*.*' => ['nullable', 'string'],
        ];
    }

    /**
     * Languages and field names are object keys, so they are checked here.
     */
    public static function validateContentKeys(Validator $validator, mixed $contents): void
    {
        if (! is_array($contents)) {
            return;
        }

        foreach ($contents as $lang => $fields) {
            if (! preg_match(self::LANG, (string) $lang)) {
                $validator->errors()->add("contents.$lang", 'The language code is invalid.');
            }

            foreach ((array) $fields as $field => $value) {
                if (! preg_match(self::FIELD, (string) $field)) {
                    $validator->errors()->add("contents.$lang.$field", 'The field name is invalid.');
                }
            }
        }
    }
}
