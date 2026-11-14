<?php

namespace KusikusiCMS\Acl\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use KusikusiCMS\Acl\Enums\EntityAction;
use KusikusiCMS\Acl\Services\PermissionResolver;
use KusikusiCMS\Acl\Tests\Fixtures\User;
use KusikusiCMS\Acl\Tests\TestCase;
use KusikusiCMS\Models\Entity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class EntityAclQueriesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $home = Entity::create(['id' => 'home']);
        Entity::create(['id' => 'blog', 'parent_entity_id' => 'home']);
        Entity::create(['id' => 'post1', 'parent_entity_id' => 'blog']);
        Entity::create(['id' => 'comment', 'parent_entity_id' => 'post1']);
        Entity::create(['id' => 'about', 'parent_entity_id' => 'home']);
        Entity::create(['id' => 'xY1a5G', 'parent_entity_id' => 'blog']);

        Entity::refreshAncestorRelationsOfEntity($home);
    }

    public function test_query_results_match_resolver_for_direct_role_and_guest_permissions(): void
    {
        $user = $this->makeUser();
        $user->givePermissionTo(
            $this->permission('entities.view.home.self,children'),
            $this->permission('entities.view.blog.descendants'),
        );

        $editor = Role::findOrCreate('editor', 'web');
        $editor->givePermissionTo($this->permission('entities.update.xY1a5G.self'));
        $user->assignRole($editor);

        Role::findOrCreate('guest', 'web')->givePermissionTo($this->permission('entities.view.about.self'));

        $resolver = app(PermissionResolver::class);
        $expected = Entity::query()
            ->get()
            ->filter(fn (Entity $entity): bool => $resolver->allows($user, EntityAction::View, $entity))
            ->pluck('id')
            ->all();

        $actual = Entity::query()->whereUserCan('view', $user)->pluck('id')->all();

        $this->assertEqualsCanonicalizing($expected, $actual);
        $this->assertSame(
            ['xY1a5G'],
            Entity::query()->whereUserCan('update', $user)->pluck('id')->all(),
        );
    }

    public function test_where_user_can_defaults_to_the_current_user_and_includes_guest_permissions(): void
    {
        $user = $this->makeUser();
        $user->givePermissionTo($this->permission('entities.view.home.self'));
        Role::findOrCreate('guest', 'web')->givePermissionTo($this->permission('entities.view.about.self'));
        Auth::login($user);

        $this->assertEqualsCanonicalizing(
            ['home', 'about'],
            Entity::query()->whereUserCan()->pluck('id')->all(),
        );
    }

    public function test_wildcard_child_and_descendant_scopes_only_include_non_root_entities(): void
    {
        $guest = Role::findOrCreate('guest', 'web');
        $guest->givePermissionTo($this->permission('entities.view.*.children'));

        $this->assertEqualsCanonicalizing(
            ['blog', 'about', 'post1', 'comment', 'xY1a5G'],
            Entity::query()->whereUserCan()->pluck('id')->all(),
        );

        $guest->syncPermissions($this->permission('entities.view.*.descendants'));

        $this->assertEqualsCanonicalizing(
            ['blog', 'about', 'post1', 'comment', 'xY1a5G'],
            Entity::query()->whereUserCan()->pluck('id')->all(),
        );
    }

    public function test_no_grants_produce_a_grouped_empty_result(): void
    {
        $query = Entity::query()->whereUserCan('view', $this->makeUser());

        $this->assertSame([], $query->pluck('id')->all());
        $this->assertCount(1, $query->getQuery()->wheres);
        $this->assertSame('Nested', $query->getQuery()->wheres[0]['type']);
    }

    public function test_where_user_can_does_not_change_the_selected_columns(): void
    {
        $query = Entity::query()->select(['id'])->whereUserCan('view', $this->makeUser());

        $this->assertSame(['id'], $query->getQuery()->columns);
    }

    public function test_it_combines_with_existing_entity_builder_filters(): void
    {
        $user = $this->makeUser();
        $user->givePermissionTo($this->permission('entities.view.home.descendants'));

        $entities = Entity::query()
            ->childrenOf('home')
            ->whereUserCan('view', $user)
            ->withContents()
            ->get();

        $this->assertEqualsCanonicalizing(['blog', 'about'], $entities->pluck('id')->all());
    }

    public function test_non_entity_builders_throw_a_logic_exception(): void
    {
        $this->expectException(\LogicException::class);

        User::query()->whereUserCan('view');
    }

    public function test_invalid_actions_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Entity::query()->whereUserCan('publish')->get();
    }

    private function makeUser(): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'ACL query test user',
            'email' => uniqid('acl-query-', true).'@example.test',
            'password' => 'password',
        ])->save();

        return $user;
    }

    private function permission(string $name): Permission
    {
        return Permission::findOrCreate($name, 'web');
    }
}
