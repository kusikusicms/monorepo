<?php

namespace KusikusiCMS\Api\Tests\Feature;

use KusikusiCMS\Api\Tests\Fixtures\User;
use KusikusiCMS\Api\Tests\TestCase;
use KusikusiCMS\Models\Entity;
use Laravel\Sanctum\Sanctum;

/**
 * api alone (no acl): no policy answers, so reads are allowed and writes are denied by default.
 */
class EntityAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']));
        Entity::query()->create(['id' => 'root', 'model' => 'home']);
    }

    public function test_reads_are_allowed(): void
    {
        $this->getJson('/api/v1/entities')->assertOk();
        $this->getJson('/api/v1/entities/root')->assertOk();
        $this->getJson('/api/v1/entities/root/tree')->assertOk();
    }

    public function test_writes_are_denied_by_default(): void
    {
        $this->postJson('/api/v1/entities', ['model' => 'page'])->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->patchJson('/api/v1/entities/root', ['view' => 'x'])->assertForbidden();
        $this->putJson('/api/v1/entities/root/contents/en', ['title' => 'x'])->assertForbidden();
        $this->deleteJson('/api/v1/entities/root')->assertForbidden();

        $this->assertNotNull(Entity::query()->find('root'));
    }

    public function test_unhandled_reads_can_be_denied(): void
    {
        config(['kusikusicms.api.authorization.unhandled.read' => 'deny']);

        $this->getJson('/api/v1/entities')->assertForbidden();
        $this->getJson('/api/v1/entities/root')->assertForbidden();
    }

    public function test_unhandled_writes_can_be_allowed(): void
    {
        config(['kusikusicms.api.authorization.unhandled.write' => 'allow']);

        $this->postJson('/api/v1/entities', ['model' => 'page', 'parent_entity_id' => 'root'])->assertCreated();
    }
}
