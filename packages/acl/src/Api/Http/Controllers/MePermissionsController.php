<?php

namespace KusikusiCMS\Acl\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use KusikusiCMS\Acl\Exceptions\InvalidEntityPermissionException;
use KusikusiCMS\Acl\Services\PermissionResolver;
use KusikusiCMS\Acl\Support\EntityPermission;

/**
 * GET /me/permissions: the entity permissions of the signed-in user and of the guest role, parsed.
 */
class MePermissionsController extends Controller
{
    public function __invoke(Request $request, PermissionResolver $resolver): JsonResponse
    {
        return response()->json([
            'data' => [
                'user' => $this->entityPermissions($request->user()),
                'guest' => $this->entityPermissions($resolver->guestRole()),
            ],
        ]);
    }

    /**
     * @return list<array{name: string, actions: list<string>, entity_ids: list<string>, scopes: list<string>}>
     */
    protected function entityPermissions(mixed $subject): array
    {
        if ($subject === null || ! method_exists($subject, 'getAllPermissions')) {
            return [];
        }

        $guard = (string) config('kusikusicms.acl.guard_name', 'web');
        $permissions = [];

        foreach ($subject->getAllPermissions() as $permission) {
            $name = data_get($permission, 'name');

            if (! is_string($name) || data_get($permission, 'guard_name') !== $guard) {
                continue;
            }

            try {
                $parsed = EntityPermission::parse($name);
            } catch (InvalidEntityPermissionException) {
                continue;
            }

            if ($parsed !== null) {
                $permissions[$name] = [
                    'name' => $name,
                    'actions' => $parsed->actions,
                    'entity_ids' => $parsed->entityIds,
                    'scopes' => $parsed->scopes,
                ];
            }
        }

        ksort($permissions);

        return array_values($permissions);
    }
}
