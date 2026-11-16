<?php

namespace KusikusiCMS\Acl\Tests\Feature;

use Illuminate\Support\Facades\DB;
use KusikusiCMS\Acl\Enums\EntityAction;
use KusikusiCMS\Acl\Services\PermissionResolver;
use KusikusiCMS\Acl\Tests\Fixtures\User;
use KusikusiCMS\Acl\Tests\Fixtures\UserWithoutRoles;
use KusikusiCMS\Acl\Tests\TestCase;
use KusikusiCMS\Models\Entity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionResolverTest extends TestCase
{
    private PermissionResolver $resolver;

    private Entity $home;

    private Entity $blog;

    private Entity $post;

    private Entity $comment;

    private Entity $about;

    private Entity $mixedCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->home = Entity::create(['id' => 'home']);
        $this->blog = Entity::create(['id' => 'blog', 'parent_entity_id' => 'home']);
        $this->post = Entity::create(['id' => 'post1', 'parent_entity_id' => 'blog']);
        $this->comment = Entity::create(['id' => 'comment', 'parent_entity_id' => 'post1']);
        $this->about = Entity::create(['id' => 'about', 'parent_entity_id' => 'home']);
        $this->mixedCase = Entity::create(['id' => 'xY1a5G', 'parent_entity_id' => 'blog']);

        Entity::refreshAncestorRelationsOfEntity($this->home);
        $this->resolver = app(PermissionResolver::class);
    }

    public function test_self_scope_matches_only_the_entity_for_every_action(): void
    {
        foreach (EntityAction::cases() as $action) {
            $user = $this->userWithPermission("entities.{$action->value}.blog.self");

            $this->assertTrue($this->resolver->allows($user, $action, $this->blog));
            $this->assertFalse($this->resolver->allows($user, $action, $this->post));
        }
    }

    public function test_children_scope_matches_only_direct_children_for_every_action(): void
    {
        foreach (EntityAction::cases() as $action) {
            $user = $this->userWithPermission("entities.{$action->value}.blog.children");

            $this->assertFalse($this->resolver->allows($user, $action, $this->blog));
            $this->assertTrue($this->resolver->allows($user, $action, $this->post));
            $this->assertTrue($this->resolver->allows($user, $action, $this->mixedCase));
            $this->assertFalse($this->resolver->allows($user, $action, $this->comment));
        }
    }

    public function test_descendants_scope_matches_any_depth_but_not_the_anchor(): void
    {
        foreach (EntityAction::cases() as $action) {
            $user = $this->userWithPermission("entities.{$action->value}.blog.descendants");

            $this->assertFalse($this->resolver->allows($user, $action, $this->blog));
            $this->assertTrue($this->resolver->allows($user, $action, $this->post));
            $this->assertTrue($this->resolver->allows($user, $action, $this->comment));
            $this->assertTrue($this->resolver->allows($user, $action, $this->mixedCase));
        }
    }

    public function test_wildcards_subparts_and_short_permission_prefixes_follow_spatie(): void
    {
        $wildcardUser = $this->userWithPermission('entities.update.*.*');

        $this->assertTrue($this->resolver->allows($wildcardUser, EntityAction::Update, $this->about));
        $this->assertFalse($this->resolver->allows($wildcardUser, EntityAction::Delete, $this->about));

        $allActionsUser = $this->userWithPermission('entities.*.*.*');

        foreach (EntityAction::cases() as $action) {
            $this->assertTrue($this->resolver->allows($allActionsUser, $action, $this->about));
        }

        $subpartUser = $this->userWithPermission('entities.create,update.home.self,children');

        $this->assertTrue($this->resolver->allows($subpartUser, EntityAction::Update, $this->home));
        $this->assertTrue($this->resolver->allows($subpartUser, EntityAction::Update, $this->blog));
        $this->assertFalse($this->resolver->allows($subpartUser, EntityAction::Delete, $this->home));

        $shortUser = $this->userWithPermission('entities.view');

        $this->assertTrue($this->resolver->allows($shortUser, EntityAction::View, $this->comment));

        $prefixUser = $this->userWithPermission('entities');

        $this->assertTrue($this->resolver->allows($prefixUser, EntityAction::Delete, $this->comment));
    }

    public function test_entity_ids_are_case_sensitive(): void
    {
        $user = $this->userWithPermission('entities.view.xy1a5g.self');

        $this->assertFalse($this->resolver->allows($user, EntityAction::View, $this->mixedCase));
    }

    public function test_it_checks_direct_user_permissions_and_role_permissions(): void
    {
        $user = $this->makeUser();
        $user->givePermissionTo($this->permission('entities.view.blog.self'));

        $this->assertTrue($this->resolver->allows($user, EntityAction::View, $this->blog));

        $role = Role::findOrCreate('editor', 'web');
        $role->givePermissionTo($this->permission('entities.delete.blog.children'));
        $user->assignRole($role);

        $this->assertTrue($this->resolver->allows($user, EntityAction::Delete, $this->post));
    }

    public function test_guest_permissions_apply_to_guests_and_authenticated_users(): void
    {
        $guest = Role::findOrCreate('guest', 'web');
        $guest->givePermissionTo($this->permission('entities.view.*.*'));

        $this->assertTrue($this->resolver->allows(null, EntityAction::View, $this->comment));
        $this->assertTrue($this->resolver->allows($this->makeUser(), EntityAction::View, $this->comment));
    }

    public function test_authenticated_users_without_has_roles_receive_only_guest_permissions(): void
    {
        $guest = Role::findOrCreate('guest', 'web');
        $guest->givePermissionTo($this->permission('entities.view.*.*'));
        $user = new UserWithoutRoles;

        $this->assertTrue($this->resolver->allows($user, EntityAction::View, $this->post));
        $this->assertFalse($this->resolver->allows($user, EntityAction::Update, $this->post));
    }

    public function test_missing_or_disabled_guest_role_grants_nothing(): void
    {
        $this->assertFalse($this->resolver->allows(null, EntityAction::View, $this->home));

        Role::findOrCreate('guest', 'web')->givePermissionTo($this->permission('entities.view.*.*'));
        config(['kusikusicms.acl.guest_role' => null]);

        $this->assertFalse($this->resolver->allows(null, EntityAction::View, $this->home));
    }

    public function test_create_permissions_are_checked_against_the_parent(): void
    {
        $childrenUser = $this->userWithPermission('entities.create.home.children');

        $this->assertTrue($this->resolver->allowsCreate($childrenUser, $this->home));
        $this->assertFalse($this->resolver->allowsCreate($childrenUser, $this->blog));

        $descendantUser = $this->userWithPermission('entities.create.home.descendants');

        $this->assertTrue($this->resolver->allowsCreate($descendantUser, $this->home));
        $this->assertTrue($this->resolver->allowsCreate($descendantUser, $this->blog));
        $this->assertTrue($this->resolver->allowsCreate($descendantUser, $this->post));

        $selfUser = $this->userWithPermission('entities.create.home.self');

        $this->assertFalse($this->resolver->allowsCreate($selfUser, $this->home));
        $this->assertFalse($this->resolver->allowsCreate($selfUser, null));
    }

    public function test_root_creation_requires_a_full_wildcard(): void
    {
        $user = $this->userWithPermission('entities.create.*.*');
        $shortPermissionUser = $this->userWithPermission('entities.create');

        $this->assertTrue($this->resolver->allowsCreate($user, null));
        $this->assertTrue($this->resolver->allowsCreate($shortPermissionUser, null));
        $this->assertFalse($this->resolver->allowsCreate($this->makeUser(), null));
    }

    public function test_ancestor_queries_are_cached_per_entity(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $this->resolver->candidates(EntityAction::View, 'comment');
            $this->resolver->candidates(EntityAction::View, 'comment');

            $relationQueries = collect(DB::getQueryLog())
                ->filter(static fn (array $query): bool => str_contains($query['query'], 'entities_relations'));

            $this->assertCount(1, $relationQueries);
        } finally {
            DB::disableQueryLog();
        }
    }

    private function userWithPermission(string $name): User
    {
        $user = $this->makeUser();
        $user->givePermissionTo($this->permission($name));

        return $user;
    }

    private function makeUser(): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'ACL test user',
            'email' => uniqid('acl-test-', true).'@example.test',
            'password' => 'password',
        ])->save();

        return $user;
    }

    private function permission(string $name): Permission
    {
        return Permission::findOrCreate($name, 'web');
    }
}
