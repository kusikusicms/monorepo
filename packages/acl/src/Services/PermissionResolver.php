<?php

namespace KusikusiCMS\Acl\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use KusikusiCMS\Acl\Enums\EntityAction;
use KusikusiCMS\Acl\Enums\EntityScope;
use KusikusiCMS\Acl\Support\EntityPermission;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\EntityRelation;
use Spatie\Permission\Models\Role;

class PermissionResolver
{
    /** @var array<string, list<string>> */
    private array $ancestorCache = [];

    /** @var array<string, Entity|null> */
    private array $entityCache = [];

    /** @var array<string, Role|null> */
    private array $guestRoleCache = [];

    public function allows(?Authenticatable $user, EntityAction $action, Entity|string $entity): bool
    {
        return $this->matchingCandidate($user, $this->candidatesFor($action, $entity)) !== null;
    }

    public function allowsCreate(?Authenticatable $user, Entity|string|null $parent): bool
    {
        return $this->matchingCreatePermission($user, $parent) !== null;
    }

    /**
     * Return the permission candidate that allows creating a child of the parent (null parent = a root entity).
     */
    public function matchingCreatePermission(?Authenticatable $user, Entity|string|null $parent): ?string
    {
        if ($parent === null) {
            return $this->matchingCandidate($user, [$this->permissionName(EntityAction::Create, '*', '*')]);
        }

        $parentEntity = $parent instanceof Entity ? $parent : $this->findEntity($parent);

        if ($parentEntity === null) {
            return null;
        }

        $parentId = (string) $parentEntity->getKey();
        $ancestors = $this->ancestorIds($parentId);
        $directParentId = $parentEntity->parent_entity_id;

        if ($directParentId !== null && ! in_array($directParentId, $ancestors, true)) {
            array_unshift($ancestors, $directParentId);
        }

        $candidates = [$this->permissionName(EntityAction::Create, $parentId, EntityScope::Children->value)];

        foreach (array_values(array_unique([$parentId, ...$ancestors])) as $ancestorId) {
            $candidates[] = $this->permissionName(EntityAction::Create, $ancestorId, EntityScope::Descendants->value);
        }

        return $this->matchingCandidate($user, $candidates);
    }

    /**
     * Return a permission candidate accepted by Spatie for the entity.
     */
    public function matchingPermission(?Authenticatable $user, EntityAction $action, Entity|string $entity): ?string
    {
        return $this->matchingCandidate($user, $this->candidatesFor($action, $entity));
    }

    /**
     * @return list<string>
     */
    public function candidates(EntityAction $action, string $entityId): array
    {
        return $this->candidatesFor($action, $entityId);
    }

    public function guestRole(): ?Role
    {
        $roleName = config('kusikusicms.acl.guest_role', 'guest');

        if (! is_string($roleName) || $roleName === '') {
            return null;
        }

        $guardName = $this->guardName();
        $cacheKey = $guardName.'|'.$roleName;

        // Only a found role is memoized, so a guest role created later in the process is picked up.
        if (! isset($this->guestRoleCache[$cacheKey])) {
            $this->guestRoleCache[$cacheKey] = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', $guardName)
                ->first();
        }

        return $this->guestRoleCache[$cacheKey];
    }

    public function hasAnyPermission(?Authenticatable $user, EntityAction $action): bool
    {
        foreach ([$user, $this->guestRole()] as $subject) {
            if ($subject === null || ! method_exists($subject, 'getAllPermissions')) {
                continue;
            }

            foreach ($subject->getAllPermissions() as $permission) {
                if (data_get($permission, 'guard_name') !== $this->guardName()) {
                    continue;
                }

                $name = data_get($permission, 'name');

                if (is_string($name) && EntityPermission::parse($name)?->allows($action)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function clearCache(): void
    {
        $this->ancestorCache = [];
        $this->entityCache = [];
    }

    /**
     * @return list<string>
     */
    private function candidatesFor(EntityAction $action, Entity|string $entity): array
    {
        $entityId = $entity instanceof Entity ? (string) $entity->getKey() : $entity;
        $entityModel = $entity instanceof Entity ? $entity : $this->findEntity($entityId);
        $candidates = [$this->permissionName($action, $entityId, EntityScope::Self->value)];

        if ($entityModel === null) {
            return $candidates;
        }

        $parentId = $entityModel->parent_entity_id;

        if ($parentId !== null) {
            $candidates[] = $this->permissionName($action, $parentId, EntityScope::Children->value);
        }

        $ancestors = $this->ancestorIds($entityId);

        if ($parentId !== null && ! in_array($parentId, $ancestors, true)) {
            array_unshift($ancestors, $parentId);
        }

        foreach ($ancestors as $ancestorId) {
            $candidates[] = $this->permissionName($action, $ancestorId, EntityScope::Descendants->value);
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @param  list<string>  $candidates
     */
    private function matchingCandidate(?Authenticatable $user, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if ($this->subjectCan($user, $candidate) || $this->subjectCan($this->guestRole(), $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function subjectCan(Authenticatable|Role|null $subject, string $candidate): bool
    {
        if ($subject === null || ! method_exists($subject, 'checkPermissionTo')) {
            return false;
        }

        return $subject->checkPermissionTo($candidate, $this->guardName());
    }

    /**
     * @return list<string>
     */
    private function ancestorIds(string $entityId): array
    {
        if (! array_key_exists($entityId, $this->ancestorCache)) {
            $this->ancestorCache[$entityId] = EntityRelation::query()
                ->where('caller_entity_id', $entityId)
                ->where('kind', EntityRelation::RELATION_ANCESTOR)
                ->orderBy('depth')
                ->pluck('called_entity_id')
                ->map(static fn (mixed $ancestorId): string => (string) $ancestorId)
                ->all();
        }

        return $this->ancestorCache[$entityId];
    }

    private function findEntity(string $entityId): ?Entity
    {
        if (! array_key_exists($entityId, $this->entityCache)) {
            $this->entityCache[$entityId] = Entity::query()
                ->select(['id', 'parent_entity_id'])
                ->find($entityId);
        }

        return $this->entityCache[$entityId];
    }

    private function permissionName(EntityAction $action, string $entityId, string $scope): string
    {
        return EntityPermission::make($action, $entityId, $scope)->name();
    }

    private function guardName(): string
    {
        $guardName = config('kusikusicms.acl.guard_name', 'web');

        return is_string($guardName) && $guardName !== '' ? $guardName : 'web';
    }
}
