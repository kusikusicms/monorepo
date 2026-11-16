<?php

namespace KusikusiCMS\Acl\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use KusikusiCMS\Acl\Enums\EntityAction;
use KusikusiCMS\Acl\Services\PermissionResolver;

class PermissionParser
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    /**
     * @return array{
     *     all: bool,
     *     non_root: bool,
     *     self_ids: list<string>,
     *     children_ids: list<string>,
     *     descendant_ids: list<string>
     * }
     */
    public function grants(string $action, ?Authenticatable $user = null): array
    {
        $actionName = $action;
        $action = EntityAction::tryFrom($actionName);

        if ($action === null) {
            throw new InvalidArgumentException("Unsupported entity action [{$actionName}].");
        }

        $grants = [
            'all' => false,
            'non_root' => false,
            'self_ids' => [],
            'children_ids' => [],
            'descendant_ids' => [],
        ];

        foreach ($this->permissionNames($user) as $name) {
            $permission = EntityPermission::parse($name);

            if ($permission === null || ! $permission->allows($action)) {
                continue;
            }

            $scopes = in_array('*', $permission->scopes, true)
                ? ['self', 'children', 'descendants']
                : $permission->scopes;

            if (in_array('*', $permission->entityIds, true)) {
                if (in_array('self', $scopes, true)) {
                    $grants['all'] = true;

                    return $grants;
                }

                $grants['non_root'] = $grants['non_root'] || $scopes !== [];

                continue;
            }

            foreach ($permission->entityIds as $entityId) {
                foreach ($scopes as $scope) {
                    if ($scope === 'self') {
                        $grants['self_ids'][] = $entityId;
                    } elseif ($scope === 'children') {
                        $grants['children_ids'][] = $entityId;
                    } elseif ($scope === 'descendants') {
                        $grants['descendant_ids'][] = $entityId;
                    }
                }
            }
        }

        foreach (['self_ids', 'children_ids', 'descendant_ids'] as $key) {
            $grants[$key] = array_values(array_unique($grants[$key]));
        }

        return $grants;
    }

    /**
     * @return list<string>
     */
    private function permissionNames(?Authenticatable $user): array
    {
        $names = [];

        foreach ([$user, $this->resolver->guestRole()] as $subject) {
            if ($subject === null || ! method_exists($subject, 'getAllPermissions')) {
                continue;
            }

            foreach ($subject->getAllPermissions() as $permission) {
                if (data_get($permission, 'guard_name') !== config('kusikusicms.acl.guard_name', 'web')) {
                    continue;
                }

                $name = data_get($permission, 'name');

                if (is_string($name)) {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }
}
