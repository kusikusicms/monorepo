<?php

namespace KusikusiCMS\Acl\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use KusikusiCMS\Acl\Enums\EntityAction;
use KusikusiCMS\Acl\Services\PermissionResolver;
use KusikusiCMS\Models\Entity;

class EntityPolicy
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    public function viewAny(?Authenticatable $user): bool
    {
        return $this->resolver->hasAnyPermission($user, EntityAction::View);
    }

    public function view(?Authenticatable $user, Entity $entity): bool
    {
        return $this->resolver->allows($user, EntityAction::View, $entity);
    }

    public function create(?Authenticatable $user, Entity|string|null $parent = null): bool
    {
        return $this->resolver->allowsCreate($user, $parent);
    }

    public function update(?Authenticatable $user, Entity $entity): bool
    {
        return $this->resolver->allows($user, EntityAction::Update, $entity);
    }

    public function delete(?Authenticatable $user, Entity $entity): bool
    {
        return $this->resolver->allows($user, EntityAction::Delete, $entity);
    }

    public function restore(?Authenticatable $user, Entity $entity): bool
    {
        return $this->delete($user, $entity);
    }

    public function forceDelete(?Authenticatable $user, Entity $entity): bool
    {
        return $this->delete($user, $entity);
    }
}
