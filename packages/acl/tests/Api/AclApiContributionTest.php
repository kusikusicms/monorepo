<?php

namespace KusikusiCMS\Acl\Tests\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Acl\Services\PermissionResolver;
use KusikusiCMS\Models\Entity;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AclApiContributionTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Entity::query()->create(['id' => 'root', 'model' => 'home']);
        Entity::query()->create(['id' => 'blog', 'model' => 'section', 'parent_entity_id' => 'root']);
        Entity::query()->create(['id' => 'post1', 'model' => 'post', 'parent_entity_id' => 'blog']);
        Entity::query()->create(['id' => 'post2', 'model' => 'post', 'parent_entity_id' => 'blog']);
        Entity::query()->create(['id' => 'about', 'model' => 'page', 'parent_entity_id' => 'root']);
    }

    public function test_can_flags_agree_with_the_policy(): void
    {
        $user = $this->userWith('entities.view', 'entities.update.blog.descendants', 'entities.create.blog.children', 'entities.delete.post1.self');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/entities?per_page=100')->assertOk();

        foreach ($response->json('data') as $item) {
            $entity = Entity::query()->findOrFail($item['id']);

            $this->assertSame([
                'view' => Gate::forUser($user)->allows('view', $entity),
                'update' => Gate::forUser($user)->allows('update', $entity),
                'delete' => Gate::forUser($user)->allows('delete', $entity),
                'create_child' => Gate::forUser($user)->allows('create', [Entity::class, $entity]),
            ], $item['can'], "Flags of {$item['id']}");
        }

        $this->getJson('/api/v1/entities/post1')
            ->assertJsonPath('data.can', ['view' => true, 'update' => true, 'delete' => true, 'create_child' => false]);
        $this->getJson('/api/v1/entities/blog')
            ->assertJsonPath('data.can', ['view' => true, 'update' => false, 'delete' => false, 'create_child' => true]);
    }

    public function test_lists_are_filtered_by_view_permission(): void
    {
        Sanctum::actingAs($this->userWith('entities.view.blog.descendants'));

        $this->getJson('/api/v1/entities?sort=id')->assertOk()->assertJsonPath('data.*.id', ['post1', 'post2']);
        $this->getJson('/api/v1/entities/blog/children?sort=id')->assertForbidden();
        $this->getJson('/api/v1/entities/post1/siblings')->assertOk()->assertJsonPath('data.*.id', ['post2']);
    }

    public function test_the_guest_role_applies(): void
    {
        Role::findOrCreate('guest', 'web')->givePermissionTo(Permission::findOrCreate('entities.view.about.self', 'web'));
        Sanctum::actingAs($this->userWith());

        $this->getJson('/api/v1/entities')->assertOk()->assertJsonPath('data.*.id', ['about']);
    }

    public function test_can_flags_run_a_constant_number_of_queries(): void
    {
        Sanctum::actingAs($this->userWith('entities.view', 'entities.update.blog.descendants'));

        // Warm up: the first request loads the user's roles and permissions once.
        $this->countQueries('/api/v1/entities?per_page=1');
        $small = $this->countQueries('/api/v1/entities?per_page=1');

        foreach (range(1, 15) as $i) {
            Entity::query()->create(['id' => "p$i", 'model' => 'post', 'parent_entity_id' => 'blog']);
        }

        $this->assertSame($small, $this->countQueries('/api/v1/entities?per_page=50'));
    }

    public function test_me_permissions_lists_user_and_guest_entity_permissions(): void
    {
        Role::findOrCreate('guest', 'web')->givePermissionTo(Permission::findOrCreate('entities.view.*.*', 'web'));
        $user = $this->userWith('entities.create,update.blog.self,descendants', 'reports.view');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/permissions')
            ->assertOk()
            ->assertExactJson(['data' => [
                'user' => [[
                    'name' => 'entities.create,update.blog.self,descendants',
                    'actions' => ['create', 'update'],
                    'entity_ids' => ['blog'],
                    'scopes' => ['self', 'descendants'],
                ]],
                'guest' => [[
                    'name' => 'entities.view.*.*',
                    'actions' => ['view'],
                    'entity_ids' => ['*'],
                    'scopes' => ['*'],
                ]],
                'can_manage' => false,
            ]]);
    }

    public function test_me_permissions_reports_can_manage_for_a_manager(): void
    {
        Sanctum::actingAs($this->userWith('kusikusicms.acl.manage'));

        $this->getJson('/api/v1/me/permissions')->assertOk()->assertJsonPath('data.can_manage', true);
    }

    public function test_me_permissions_reports_can_manage_false_for_a_non_manager(): void
    {
        Sanctum::actingAs($this->userWith('entities.view'));

        $this->getJson('/api/v1/me/permissions')->assertOk()->assertJsonPath('data.can_manage', false);
    }

    public function test_me_permissions_requires_authentication(): void
    {
        $this->getJson('/api/v1/me/permissions')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }

    public function test_the_capability_is_registered(): void
    {
        $this->getJson('/api/v1/capabilities')
            ->assertJsonPath('data.packages.1.name', 'acl')
            ->assertJsonPath('data.packages.1.features', ['can-flags', 'filtered-lists', 'me.permissions', 'roles', 'permissions.manage', 'entity-access', 'users']);
    }

    protected function countQueries(string $uri): int
    {
        // Each real request starts with an empty resolver cache.
        $this->app->make(PermissionResolver::class)->clearCache();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($uri)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
