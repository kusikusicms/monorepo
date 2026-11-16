<?php

namespace KusikusiCMS\Acl\Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use KusikusiCMS\Acl\Acl;
use KusikusiCMS\Acl\Tests\Fixtures\User;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\EntityContent;
use Spatie\Permission\Models\Permission;

class AuthorizeEntityWritesTest extends GuardTestCase
{
    public function test_guard_rejects_unauthorized_entity_creation_and_allows_authorized_creation(): void
    {
        $home = $this->entity('home');
        $this->useHttpContext();

        try {
            Entity::create(['id' => 'blocked', 'parent_entity_id' => 'home']);
            $this->fail('An unauthorized entity creation should have been rejected.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('entities', ['id' => 'blocked']);
        }

        Auth::login($this->userWithPermission('entities.create.home.children'));

        $child = Entity::create(['id' => 'allowed', 'parent_entity_id' => $home->id]);

        $this->assertSame('allowed', $child->id);
    }

    public function test_guard_rejects_updates_and_allows_authorized_updates(): void
    {
        $entity = $this->entity('home');
        $this->useHttpContext();

        try {
            $entity->update(['model' => 'Blocked']);
            $this->fail('An unauthorized entity update should have been rejected.');
        } catch (AuthorizationException) {
            $this->assertSame('Entity', $entity->fresh()->model);
        }

        Auth::login($this->userWithPermission('entities.update.home.self'));

        $entity->update(['model' => 'Allowed']);

        $this->assertSame('Allowed', $entity->fresh()->model);
    }

    public function test_parent_move_requires_create_permission_under_the_new_parent(): void
    {
        $home = $this->entity('home');
        $oldParent = $this->entity('old', $home->id);
        $newParent = $this->entity('new', $home->id);
        $child = $this->entity('child', $oldParent->id);
        Entity::refreshAncestorRelationsOfEntity($home);

        $user = $this->userWithPermission('entities.update.old.children');
        Auth::login($user);
        $this->useHttpContext();
        $child->parent_entity_id = $newParent->id;

        try {
            $child->save();
            $this->fail('A parent move without create permission should have been rejected.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('create', $exception->getMessage());
        }

        $user->givePermissionTo($this->permission('entities.create.new.children'));
        $child->save();

        $this->assertSame($newParent->id, $child->fresh()->parent_entity_id);
    }

    public function test_delete_and_restore_require_the_delete_action(): void
    {
        $entity = $this->entity('home');
        $this->useHttpContext();

        try {
            $entity->delete();
            $this->fail('An unauthorized delete should have been rejected.');
        } catch (AuthorizationException) {
            $this->assertNotSoftDeleted('entities', ['id' => 'home']);
        }

        Acl::withoutAuthorization(fn () => $entity->delete());
        $this->assertSoftDeleted('entities', ['id' => 'home']);

        try {
            $entity->restore();
            $this->fail('An unauthorized restore should have been rejected.');
        } catch (AuthorizationException) {
            $this->assertSoftDeleted('entities', ['id' => 'home']);
        }
    }

    public function test_authorized_delete_and_restore_are_allowed(): void
    {
        $entity = $this->entity('home');
        Auth::login($this->userWithPermission('entities.delete.home.self'));
        $this->useHttpContext();

        $entity->delete();
        $this->assertSoftDeleted('entities', ['id' => 'home']);

        $entity->restore();
        $this->assertNotSoftDeleted('entities', ['id' => 'home']);
    }

    public function test_content_saving_and_deleting_require_update_permission(): void
    {
        $entity = $this->entity('home');
        $this->useHttpContext();

        try {
            EntityContent::create([
                'entity_id' => $entity->id,
                'lang' => 'en',
                'field' => 'title',
                'text' => 'Blocked',
            ]);
            $this->fail('An unauthorized content save should have been rejected.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('entities_contents', ['entity_id' => $entity->id]);
        }

        Auth::login($this->userWithPermission('entities.update.home.self'));
        $content = EntityContent::create([
            'entity_id' => $entity->id,
            'lang' => 'en',
            'field' => 'title',
            'text' => 'Allowed',
        ]);

        Auth::logout();

        try {
            $content->delete();
            $this->fail('An unauthorized content delete should have been rejected.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('entities_contents', ['content_id' => $content->content_id]);
        }

        Auth::login($this->userWithPermission('entities.update.home.self'));
        $content->delete();

        $this->assertDatabaseMissing('entities_contents', ['content_id' => $content->content_id]);
    }

    public function test_guard_can_be_bypassed_explicitly(): void
    {
        $this->useHttpContext();

        $entity = Acl::withoutAuthorization(fn () => Entity::create(['id' => 'bypassed']));

        $this->assertSame('bypassed', $entity->id);
        $this->assertFalse(Acl::isAuthorizationBypassed());
    }

    public function test_guard_is_bypassed_in_console_context(): void
    {
        $entity = Entity::create(['id' => 'console']);

        $this->assertSame('console', $entity->id);
    }

    private function entity(string $id, ?string $parentEntityId = null): Entity
    {
        return Entity::create([
            'id' => $id,
            'parent_entity_id' => $parentEntityId,
        ]);
    }

    private function userWithPermission(string $name): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'ACL guard test user',
            'email' => uniqid('acl-guard-', true).'@example.test',
            'password' => 'password',
        ])->save();
        $user->givePermissionTo($this->permission($name));

        return $user;
    }

    private function permission(string $name): Permission
    {
        return Permission::findOrCreate($name, 'web');
    }

    private function useHttpContext(): void
    {
        $runningInConsole = new \ReflectionProperty(Application::class, 'isRunningInConsole');
        $runningInConsole->setValue($this->app, false);
    }
}
