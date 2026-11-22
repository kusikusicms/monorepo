<?php

namespace KusikusiCMS\Acl\Api\Http\Requests\Concerns;

use Closure;
use Illuminate\Validation\Validator;
use KusikusiCMS\Acl\Exceptions\InvalidEntityPermissionException;
use KusikusiCMS\Acl\Support\EntityPermission;
use KusikusiCMS\Models\Entity;
use TypeError;

/**
 * Builds EntityPermission objects from `{action, entity_id, scope}` payloads, turning parser errors into
 * validation errors, and checks that every concrete entity id exists with one query.
 */
trait ParsesEntityPermissions
{
    /** @var array<string, EntityPermission> Keyed by the payload path ('' for the root). */
    private array $parsedPermissions = [];

    /**
     * @return array<int, mixed>
     */
    protected function permissionPartRules(): array
    {
        return ['required', function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) && ! is_array($value)) {
                $fail("The {$attribute} field must be a string or a list of strings.");
            }
        }];
    }

    /**
     * @param  array<string, mixed>  $payloads  Keyed by the payload path ('' for the root).
     */
    protected function parsePermissions(Validator $validator, array $payloads): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        foreach ($payloads as $path => $payload) {
            $prefix = $path === '' ? '' : $path.'.';

            try {
                $this->parsedPermissions[$path] = EntityPermission::make(
                    $payload['action'] ?? [],
                    $payload['entity_id'] ?? [],
                    $payload['scope'] ?? [],
                );
            } catch (InvalidEntityPermissionException|TypeError $e) {
                $validator->errors()->add($prefix.'permission', $e instanceof InvalidEntityPermissionException
                    ? $e->getMessage()
                    : 'Invalid entity permission.');
            }
        }

        $ids = [];
        foreach ($this->parsedPermissions as $permission) {
            foreach ($permission->entityIds as $id) {
                if ($id !== '*') {
                    $ids[$id] = true;
                }
            }
        }

        if ($ids === []) {
            return;
        }

        $existing = Entity::query()->withTrashed()->whereIn('id', array_keys($ids))->pluck('id')->all();
        $missing = array_diff(array_keys($ids), $existing);

        foreach ($this->parsedPermissions as $path => $permission) {
            $unknown = array_values(array_intersect($permission->entityIds, $missing));

            if ($unknown !== []) {
                $prefix = $path === '' ? '' : $path.'.';
                $validator->errors()->add($prefix.'entity_id', 'Unknown entity ids: '.implode(', ', $unknown).'.');
            }
        }
    }

    /**
     * @return array<string, EntityPermission>
     */
    protected function parsedPermissions(): array
    {
        return $this->parsedPermissions;
    }
}
