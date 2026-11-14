<?php

namespace KusikusiCMS\Acl\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Acl\Enums\EntityAction;
use KusikusiCMS\Acl\Services\PermissionResolver;
use KusikusiCMS\Acl\Tests\Fixtures\User;
use KusikusiCMS\Acl\Tests\TestCase;
use KusikusiCMS\Models\Entity;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Keeps docs/permissions.md and docs/recipes.md true: every documented example runs here
 * against the sample tree of the docs.
 *
 * home
 * ├── blog
 * │   ├── post1
 * │   │   └── comment1
 * │   └── post2
 * ├── members
 * │   └── guide
 * └── aBc12
 * ln65
 * └── old1
 */
class DocumentationExamplesTest extends TestCase
{
    private const ALL = ['home', 'blog', 'post1', 'comment1', 'post2', 'members', 'guide', 'aBc12', 'ln65', 'old1'];

    private PermissionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'home' => null, 'blog' => 'home', 'post1' => 'blog', 'comment1' => 'post1', 'post2' => 'blog',
            'members' => 'home', 'guide' => 'members', 'aBc12' => 'home', 'ln65' => null, 'old1' => 'ln65',
        ] as $id => $parent) {
            Entity::create(['id' => $id, 'parent_entity_id' => $parent]);
        }
        Entity::refreshAncestorRelationsOfEntity(Entity::find('home'));
        Entity::refreshAncestorRelationsOfEntity(Entity::find('ln65'));

        $this->resolver = app(PermissionResolver::class);
    }

    /**
     * The table of docs/permissions.md: permission => [action => entities allowed (for create: allowed parents)].
     *
     * @return array<string, array{0: string, 1: array<string, list<string>>}>
     */
    public static function permissionTable(): array
    {
        return [
            'view everything' => ['entities.view', [
                'view' => self::ALL, 'update' => [], 'create' => [],
            ]],
            'create children of blog' => ['entities.create.blog.children', [
                'create' => ['blog'], 'view' => [],
            ]],
            'update one entity' => ['entities.update.aBc12.self', [
                'update' => ['aBc12'], 'view' => [],
            ]],
            'delete children of ln65' => ['entities.delete.ln65.children', [
                'delete' => ['old1'],
            ]],
            'create and update home and its children' => ['entities.create,update.home.self,children', [
                'update' => ['home', 'blog', 'members', 'aBc12'], 'create' => ['home'], 'delete' => [],
            ]],
            'everything on the blog branch' => ['entities.*.blog.self,descendants', [
                'view' => ['blog', 'post1', 'comment1', 'post2'],
                'update' => ['blog', 'post1', 'comment1', 'post2'],
                'delete' => ['blog', 'post1', 'comment1', 'post2'],
                'create' => ['blog', 'post1', 'comment1', 'post2'],
            ]],
        ];
    }

    /**
     * @param  array<string, list<string>>  $expected
     */
    #[DataProvider('permissionTable')]
    public function test_documented_permission_table(string $permission, array $expected): void
    {
        $user = $this->userWith([$permission]);

        foreach ($expected as $action => $allowed) {
            $this->assertEqualsCanonicalizing($allowed, $this->allowed($user, $action), "{$permission} / {$action}");
        }
    }

    public function test_recipe_public_website(): void
    {
        $this->guestWith(['entities.view.*.*']);

        $this->assertEqualsCanonicalizing(self::ALL, $this->allowed(null, 'view'));
        $this->assertEqualsCanonicalizing(self::ALL, $this->allowed($this->userWith([]), 'view'));
        $this->assertSame([], $this->allowed(null, 'update'));
    }

    public function test_recipe_members_only_section(): void
    {
        $this->guestWith(['entities.view.home.self', 'entities.view.blog,aBc12.self,descendants']);
        $member = $this->userWith([], 'member', ['entities.view.members.self,descendants']);

        $this->assertFalse(Gate::forUser(null)->allows('view', Entity::find('guide')));
        $this->assertTrue(Gate::forUser($member)->allows('view', Entity::find('guide')));
        $this->assertTrue(Gate::forUser($member)->allows('view', Entity::find('post1')));
        $this->assertEqualsCanonicalizing(['home', 'blog', 'post1', 'comment1', 'post2', 'aBc12'], $this->allowed(null, 'view'));
    }

    public function test_recipe_blog_authors(): void
    {
        $author = $this->userWith([], 'author', ['entities.view,update.blog.self', 'entities.*.blog.descendants']);

        $this->assertTrue(Gate::forUser($author)->allows('create', [Entity::class, 'blog']));
        $this->assertTrue(Gate::forUser($author)->allows('create', [Entity::class, 'post1']));
        $this->assertTrue(Gate::forUser($author)->allows('update', Entity::find('blog')));
        $this->assertTrue(Gate::forUser($author)->allows('delete', Entity::find('post1')));
        $this->assertFalse(Gate::forUser($author)->allows('delete', Entity::find('blog')));
        $this->assertFalse(Gate::forUser($author)->allows('update', Entity::find('home')));
        $this->assertFalse(Gate::forUser($author)->allows('create', [Entity::class, 'home']));
    }

    public function test_recipe_editor_updates_but_does_not_delete(): void
    {
        $editor = $this->userWith([], 'editor', ['entities.view,update.*.*']);

        $this->assertEqualsCanonicalizing(self::ALL, $this->allowed($editor, 'update'));
        $this->assertSame([], $this->allowed($editor, 'delete'));
        $this->assertSame([], $this->allowed($editor, 'create'));
    }

    public function test_recipe_private_branch_hidden_from_listings(): void
    {
        $this->guestWith(['entities.view.home.self,descendants']);
        $admin = $this->userWith(['entities']);

        $this->assertNotContains('ln65', Entity::query()->whereUserCan('view')->pluck('id')->all());
        $this->assertNotContains('old1', Entity::query()->whereUserCan('view')->pluck('id')->all());
        $this->assertEqualsCanonicalizing(self::ALL, Entity::query()->whereUserCan('view', $admin)->pluck('id')->all());
    }

    public function test_lists_and_single_checks_agree_for_every_documented_permission(): void
    {
        foreach (self::permissionTable() as [$permission]) {
            $user = $this->userWith([$permission]);
            foreach (['view', 'update', 'delete'] as $action) {
                $this->assertEqualsCanonicalizing(
                    $this->allowed($user, $action),
                    Entity::query()->whereUserCan($action, $user)->pluck('id')->all(),
                    "{$permission} / {$action}",
                );
            }
        }
    }

    /**
     * The entities the subject may act on; for create, the parents it may create children under.
     *
     * @return list<string>
     */
    private function allowed(?User $user, string $action): array
    {
        $action = EntityAction::from($action);

        return array_values(array_filter(self::ALL, fn (string $id) => $action === EntityAction::Create
            ? $this->resolver->allowsCreate($user, $id)
            : $this->resolver->allows($user, $action, $id)));
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $rolePermissions
     */
    private function userWith(array $permissions, ?string $role = null, array $rolePermissions = []): User
    {
        $user = new User;
        $user->forceFill(['name' => 'Docs', 'email' => uniqid('docs-', true).'@example.test', 'password' => 'password'])->save();

        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        if ($role !== null) {
            $model = Role::findOrCreate($role, 'web');
            foreach ($rolePermissions as $name) {
                $model->givePermissionTo(Permission::findOrCreate($name, 'web'));
            }
            $user->assignRole($model);
        }

        return $user;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function guestWith(array $permissions): void
    {
        $guest = Role::findOrCreate('guest', 'web');
        foreach ($permissions as $name) {
            $guest->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
    }
}
