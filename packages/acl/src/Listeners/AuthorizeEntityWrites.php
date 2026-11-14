<?php

namespace KusikusiCMS\Acl\Listeners;

use Illuminate\Auth\Access\AuthorizationException;
use KusikusiCMS\Acl\Acl;
use KusikusiCMS\Acl\Enums\EntityAction;
use KusikusiCMS\Acl\Services\PermissionResolver;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\EntityContent;
use KusikusiCMS\Models\Events\EntityContentDeleting;
use KusikusiCMS\Models\Events\EntityContentSaving;
use KusikusiCMS\Models\Events\EntityCreating;
use KusikusiCMS\Models\Events\EntityDeleting;
use KusikusiCMS\Models\Events\EntityRestoring;
use KusikusiCMS\Models\Events\EntityUpdating;
use WeakMap;

class AuthorizeEntityWrites
{
    /** @var WeakMap<Entity, true>|null */
    private static ?WeakMap $restoringEntities = null;

    public function __construct(private readonly PermissionResolver $resolver) {}

    public function creating(EntityCreating $event): void
    {
        if (! $this->shouldAuthorize()) {
            return;
        }

        $this->authorize(
            $this->resolver->allowsCreate(auth()->user(), $event->entity->parent_entity_id),
            'create',
        );
    }

    public function updating(EntityUpdating $event): void
    {
        if (! $this->shouldAuthorize()) {
            return;
        }

        $entity = $event->entity;

        if ($this->isRestoreUpdate($entity)) {
            return;
        }

        $original = clone $entity;
        $original->setAttribute('parent_entity_id', $entity->getRawOriginal('parent_entity_id'));

        $this->authorize($this->resolver->allows(auth()->user(), EntityAction::Update, $original), 'update');

        if ($entity->isDirty('parent_entity_id')) {
            $this->authorize(
                $this->resolver->allowsCreate(auth()->user(), $entity->parent_entity_id),
                'create',
            );
        }
    }

    public function deleting(EntityDeleting $event): void
    {
        $this->authorizeEntityAction($event->entity, EntityAction::Delete, 'delete');
    }

    public function restoring(EntityRestoring $event): void
    {
        $this->authorizeEntityAction($event->entity, EntityAction::Delete, 'restore');

        if ($this->shouldAuthorize()) {
            self::$restoringEntities ??= new WeakMap;
            self::$restoringEntities[$event->entity] = true;
        }
    }

    public function contentSaving(EntityContentSaving $event): void
    {
        $this->authorizeContent($event->content, 'save content');
    }

    public function contentDeleting(EntityContentDeleting $event): void
    {
        $this->authorizeContent($event->content, 'delete content');
    }

    private function authorizeEntityAction(Entity $entity, EntityAction $action, string $ability): void
    {
        if (! $this->shouldAuthorize()) {
            return;
        }

        $this->authorize($this->resolver->allows(auth()->user(), $action, $entity), $ability);
    }

    private function authorizeContent(EntityContent $content, string $ability): void
    {
        if (! $this->shouldAuthorize()) {
            return;
        }

        $entity = Entity::query()->find($content->entity_id);
        $user = auth()->user();

        $this->authorize(
            $entity !== null && $this->resolver->allows($user, EntityAction::Update, $entity),
            $ability,
        );
    }

    private function shouldAuthorize(): bool
    {
        return (bool) config('kusikusicms.acl.guard.enabled', false)
            && ! app()->runningInConsole()
            && ! Acl::isAuthorizationBypassed();
    }

    private function isRestoreUpdate(Entity $entity): bool
    {
        if (self::$restoringEntities === null || ! isset(self::$restoringEntities[$entity])) {
            return false;
        }

        unset(self::$restoringEntities[$entity]);

        return $entity->isDirty('deleted_at')
            && $entity->getRawOriginal('deleted_at') !== null
            && $entity->getAttribute('deleted_at') === null;
    }

    private function authorize(bool $allowed, string $ability): void
    {
        if (! $allowed) {
            throw new AuthorizationException("Not authorized to {$ability} this entity.");
        }
    }
}
