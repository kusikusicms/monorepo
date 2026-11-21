<?php

namespace KusikusiCMS\Api\Tests\Feature;

use KusikusiCMS\Api\Tests\WithAclTestCase;
use KusikusiCMS\Models\Entity;
use Laravel\Sanctum\Sanctum;

/**
 * api + acl: the endpoints follow EntityPolicy.
 */
class EntityAclAuthorizationTest extends WithAclTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Entity::query()->create(['id' => 'root', 'model' => 'home']);
        Entity::query()->create(['id' => 'blog', 'model' => 'section', 'parent_entity_id' => 'root']);
        Entity::query()->create(['id' => 'post', 'model' => 'post', 'parent_entity_id' => 'blog']);
    }

    public function test_view_requires_a_permission(): void
    {
        Sanctum::actingAs($this->userWith());

        $this->getJson('/api/v1/entities')->assertForbidden();
        $this->getJson('/api/v1/entities/post')->assertForbidden();

        Sanctum::actingAs($this->userWith('entities.view.blog.self,descendants'));

        $this->getJson('/api/v1/entities')->assertOk();
        $this->getJson('/api/v1/entities/post')->assertOk();
        $this->getJson('/api/v1/entities/root')->assertForbidden();
    }

    public function test_update_follows_the_policy(): void
    {
        Sanctum::actingAs($this->userWith('entities.view', 'entities.update.post.self'));

        $this->patchJson('/api/v1/entities/post', ['view' => 'wide'])->assertOk()->assertJsonPath('data.view', 'wide');
        $this->patchJson('/api/v1/entities/blog', ['view' => 'wide'])->assertForbidden();
        $this->putJson('/api/v1/entities/blog/contents/en', ['title' => 'x'])->assertForbidden();
        $this->deleteJson('/api/v1/entities/post')->assertForbidden();
    }

    public function test_create_is_checked_on_the_parent(): void
    {
        Sanctum::actingAs($this->userWith('entities.view', 'entities.create.blog.children'));

        $this->postJson('/api/v1/entities', ['model' => 'post', 'parent_entity_id' => 'blog'])->assertCreated();
        $this->postJson('/api/v1/entities', ['model' => 'post', 'parent_entity_id' => 'root'])->assertForbidden();
        $this->postJson('/api/v1/entities', ['model' => 'post'])->assertForbidden();
    }

    public function test_moving_requires_create_on_the_new_parent(): void
    {
        Sanctum::actingAs($this->userWith('entities.view', 'entities.update.post.self'));

        $this->patchJson('/api/v1/entities/post', ['parent_entity_id' => 'root'])->assertForbidden();

        Sanctum::actingAs($this->userWith('entities.view', 'entities.update.post.self', 'entities.create.root.children'));

        $this->patchJson('/api/v1/entities/post', ['parent_entity_id' => 'root'])->assertOk();
    }

    public function test_delete_and_restore_follow_the_policy(): void
    {
        Sanctum::actingAs($this->userWith('entities.view', 'entities.delete.post.self'));

        $this->deleteJson('/api/v1/entities/post')->assertNoContent();
        $this->postJson('/api/v1/entities/post/restore')->assertOk();
        $this->deleteJson('/api/v1/entities/blog')->assertForbidden();
    }
}
