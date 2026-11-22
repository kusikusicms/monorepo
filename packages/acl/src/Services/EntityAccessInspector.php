<?php

namespace KusikusiCMS\Acl\Services;

use KusikusiCMS\Acl\Enums\EntityAction;
use KusikusiCMS\Acl\Enums\EntityScope;
use KusikusiCMS\Acl\Exceptions\InvalidEntityPermissionException;
use KusikusiCMS\Acl\Support\EntityPermission;
use KusikusiCMS\Models\Entity;
use Spatie\Permission\Models\Role;

/**
 * Which roles can view, create, update or delete one entity, and through which permissions.
 *
 * It evaluates the same candidates as PermissionResolver: `{id}.self`, `{parent}.children` and
 * `{ancestor}.descendants` (the parent counts as an ancestor), and for `create` the entity as the parent:
 * `{id}.children` and `{id or ancestor}.descendants`. A held permission covers a candidate when each of its
 * parts contains the candidate's part or `*`, which is how Spatie's wildcards match these names. The `*`
 * super-admin permission covers everything. Runs three queries: roles, their permissions and the ancestors.
 */
final class EntityAccessInspector
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    /**
     * @return list<array{role: string, actions: array<string, bool>, via: list<array{name: string, action: list<string>, entity_id: list<string>, scope: list<string>}>}>
     */
    public function forEntity(Entity $entity): array
    {
        $candidates = $this->candidates($entity);
        $result = [];

        $roles = Role::query()
            ->where('guard_name', (string) config('kusikusicms.acl.guard_name', 'web'))
            ->with('permissions')
            ->orderBy('name')
            ->get();

        foreach ($roles as $role) {
            $actions = array_fill_keys(array_map(fn (EntityAction $a) => $a->value, EntityAction::cases()), false);
            $via = [];

            foreach ($role->permissions as $permission) {
                $name = (string) $permission->name;
                $parsed = $this->parse($name);

                if ($parsed === null) {
                    continue;
                }

                foreach ($candidates as $action => $tuples) {
                    foreach ($tuples as [$id, $scope]) {
                        if ($this->covers($parsed, $action, $id, $scope)) {
                            $actions[$action] = true;
                            $via[$name] = [
                                'name' => $name,
                                'action' => $parsed->actions,
                                'entity_id' => $parsed->entityIds,
                                'scope' => $parsed->scopes,
                            ];

                            break;
                        }
                    }
                }
            }

            $result[] = ['role' => (string) $role->name, 'actions' => $actions, 'via' => array_values($via)];
        }

        return $result;
    }

    /**
     * The (entity id, scope) pairs whose permission would grant each action on the entity.
     *
     * @return array<string, list<array{0: string, 1: string}>>
     */
    public function candidates(Entity $entity): array
    {
        $id = (string) $entity->getKey();
        $parentId = $entity->parent_entity_id !== null ? (string) $entity->parent_entity_id : null;

        $this->resolver->primeAncestors([$id]);
        $ancestors = $this->resolver->ancestorIdsOf($id);

        if ($parentId !== null && ! in_array($parentId, $ancestors, true)) {
            array_unshift($ancestors, $parentId);
        }

        $onEntity = [[$id, EntityScope::Self->value]];

        if ($parentId !== null) {
            $onEntity[] = [$parentId, EntityScope::Children->value];
        }

        foreach ($ancestors as $ancestorId) {
            $onEntity[] = [$ancestorId, EntityScope::Descendants->value];
        }

        $asParent = [[$id, EntityScope::Children->value]];

        foreach (array_values(array_unique([$id, ...$ancestors])) as $ancestorId) {
            $asParent[] = [$ancestorId, EntityScope::Descendants->value];
        }

        return [
            EntityAction::View->value => $onEntity,
            EntityAction::Create->value => $asParent,
            EntityAction::Update->value => $onEntity,
            EntityAction::Delete->value => $onEntity,
        ];
    }

    private function covers(EntityPermission $permission, string $action, string $id, string $scope): bool
    {
        return $this->contains($permission->actions, $action)
            && $this->contains($permission->entityIds, $id)
            && $this->contains($permission->scopes, $scope);
    }

    /**
     * @param  list<string>  $parts
     */
    private function contains(array $parts, string $value): bool
    {
        return in_array('*', $parts, true) || in_array($value, $parts, true);
    }

    private function parse(string $name): ?EntityPermission
    {
        if ($name === '*') {
            return EntityPermission::make('*', '*', '*');
        }

        try {
            return EntityPermission::parse($name);
        } catch (InvalidEntityPermissionException) {
            return null;
        }
    }
}
