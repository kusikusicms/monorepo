<?php

namespace KusikusiCMS\Acl\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Acl\Services\EntityAccessInspector;
use KusikusiCMS\Acl\Services\PermissionResolver;
use KusikusiCMS\Acl\Tests\Fixtures\User;
use KusikusiCMS\Acl\Tests\TestCase;
use KusikusiCMS\Models\Entity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The access view must agree with EntityPolicy for every role, action and scope.
 */
class EntityAccessInspectorTest extends TestCase
{
    /** @var list<string> */
    private array $ids = ['root', 'blog', 'post1', 'about'];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Entity::query()->create(['id' => 'root', 'model' => 'home']);
        Entity::query()->create(['id' => 'blog', 'model' => 'section', 'parent_entity_id' => 'root']);
        Entity::query()->create(['id' => 'post1', 'model' => 'post', 'parent_entity_id' => 'blog']);
        Entity::query()->create(['id' => 'about', 'model' => 'page', 'parent_entity_id' => 'root']);
    }

    public function test_the_access_view_agrees_with_the_policy(): void
    {
        $actions = ['view', 'create', 'update', 'delete', 'view,update', '*'];
        $users = [];
        $n = 0;

        foreach ([...$this->ids, '*'] as $target) {
            foreach (['self', 'children', 'descendants', '*'] as $scope) {
                $action = $actions[$n % count($actions)];
                $users[] = $this->userWithRole("r{$n}", "entities.{$action}.{$target}.{$scope}");
                $n++;
            }
        }

        $users[] = $this->userWithRole('super', '*');
        $users[] = $this->userWithRole('all-entities', 'entities');
        $users[] = $this->userWithRole('updaters', 'entities.update');
        $users[] = $this->userWithRole('unrelated', 'reports.view');
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $inspector = $this->app->make(EntityAccessInspector::class);
        $checked = 0;

        foreach ($this->ids as $id) {
            $entity = Entity::query()->findOrFail($id);
            $access = collect($inspector->forEntity($entity))->keyBy('role');

            foreach ($users as [$role, $user]) {
                $expected = [
                    'view' => Gate::forUser($user)->allows('view', $entity),
                    'create' => Gate::forUser($user)->allows('create', [Entity::class, $entity]),
                    'update' => Gate::forUser($user)->allows('update', $entity),
                    'delete' => Gate::forUser($user)->allows('delete', $entity),
                ];

                $this->assertSame($expected, $access[$role]['actions'], "Role {$role} on {$id}");
                $this->assertSame(in_array(true, $expected, true), $access[$role]['via'] !== [], "via of {$role} on {$id}");
                $checked++;
            }
        }

        $this->assertSame(count($this->ids) * count($users), $checked);
    }

    public function test_via_lists_the_matching_permissions(): void
    {
        $role = Role::findOrCreate('editor', 'web');
        $role->givePermissionTo(
            Permission::findOrCreate('entities.update.blog.descendants', 'web'),
            Permission::findOrCreate('entities.view.post1.self', 'web'),
            Permission::findOrCreate('entities.delete.about.self', 'web'),
        );

        $access = $this->app->make(EntityAccessInspector::class)->forEntity(Entity::query()->findOrFail('post1'));

        $this->assertSame([[
            'role' => 'editor',
            'actions' => ['view' => true, 'create' => false, 'update' => true, 'delete' => false],
            'via' => [
                ['name' => 'entities.update.blog.descendants', 'action' => ['update'], 'entity_id' => ['blog'], 'scope' => ['descendants']],
                ['name' => 'entities.view.post1.self', 'action' => ['view'], 'entity_id' => ['post1'], 'scope' => ['self']],
            ],
        ]], $access);
    }

    public function test_it_runs_a_fixed_number_of_queries(): void
    {
        $entity = Entity::query()->findOrFail('post1');
        Role::findOrCreate('role0', 'web')->givePermissionTo(Permission::findOrCreate('entities.view.blog.self', 'web'));
        $small = $this->countQueries($entity);

        foreach (range(1, 10) as $i) {
            Role::findOrCreate("role{$i}", 'web')->givePermissionTo(Permission::findOrCreate("entities.view.blog.{$this->scope($i)}", 'web'));
        }

        $this->assertSame($small, $this->countQueries($entity));
    }

    /**
     * @return array{0: string, 1: User}
     */
    private function userWithRole(string $roleName, string $permission): array
    {
        $role = Role::findOrCreate($roleName, 'web');
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));

        /** @var User $user */
        $user = User::query()->forceCreate(['name' => $roleName, 'email' => $roleName.'@example.com', 'password' => 'x']);
        $user->assignRole($role);

        return [$roleName, $user];
    }

    private function scope(int $i): string
    {
        return ['self', 'children', 'descendants'][$i % 3];
    }

    private function countQueries(Entity $entity): int
    {
        $this->app->make(PermissionResolver::class)->clearCache();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->app->make(EntityAccessInspector::class)->forEntity($entity);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
