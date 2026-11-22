<?php

namespace KusikusiCMS\Acl\Tests\Api;

use Illuminate\Support\Facades\DB;
use KusikusiCMS\Acl\Tests\Fixtures\ApiUser;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UsersApiTest extends ApiTestCase
{
    protected Role $admin;

    protected Role $editor;

    protected ApiUser $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Role::findOrCreate('admin', 'web');
        $this->admin->givePermissionTo(Permission::findOrCreate('kusikusicms.acl.manage', 'web'));
        $this->editor = Role::findOrCreate('editor', 'web');
        Role::findOrCreate('guest', 'web');

        $this->manager = $this->makeUser('Grace Hopper', 'grace@example.com');
        $this->manager->assignRole($this->admin);
        Sanctum::actingAs($this->manager);
    }

    public function test_non_managers_are_forbidden(): void
    {
        Sanctum::actingAs($this->userWith('entities.view'));
        $id = $this->manager->getKey();

        $this->getJson('/api/v1/users')->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->getJson("/api/v1/users/{$id}/roles")->assertForbidden();
        $this->putJson("/api/v1/users/{$id}/roles", ['roles' => []])->assertForbidden();
    }

    public function test_search_by_name_and_email_without_secret_fields(): void
    {
        $ada = $this->makeUser('Ada Lovelace', 'ada@example.com');
        $ada->assignRole($this->editor);
        $this->makeUser('Alan Turing', 'alan@turing.test');

        $this->getJson('/api/v1/users?search=lovelace')
            ->assertOk()
            ->assertExactJson([
                'data' => [['id' => $ada->getKey(), 'name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'roles' => ['editor']]],
                'links' => $this->getJson('/api/v1/users?search=lovelace')->json('links'),
                'meta' => $this->getJson('/api/v1/users?search=lovelace')->json('meta'),
            ]);

        $this->getJson('/api/v1/users?search=turing.test')->assertOk()->assertJsonPath('data.*.name', ['Alan Turing']);
        $this->getJson('/api/v1/users?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
    }

    public function test_search_uses_the_configured_columns(): void
    {
        config()->set('kusikusicms.acl.api.users.search_columns', ['name']);
        $this->makeUser('Ada Lovelace', 'ada@example.com');

        $this->getJson('/api/v1/users?search=example.com')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/users?search=Ada')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_user_search_runs_a_constant_number_of_queries(): void
    {
        $this->getJson('/api/v1/users')->assertOk();
        $small = $this->countQueries('/api/v1/users');

        foreach (range(1, 10) as $i) {
            $this->makeUser("User $i", "u$i@example.com")->assignRole($this->editor);
        }

        $this->assertSame($small, $this->countQueries('/api/v1/users'));
    }

    public function test_read_and_sync_user_roles(): void
    {
        $ada = $this->makeUser('Ada Lovelace', 'ada@example.com');
        $ada->assignRole($this->editor);

        $this->getJson("/api/v1/users/{$ada->getKey()}/roles")->assertOk()->assertExactJson(['data' => ['editor']]);

        $this->putJson("/api/v1/users/{$ada->getKey()}/roles", ['roles' => ['admin', 'editor']])
            ->assertOk()
            ->assertExactJson(['data' => ['admin', 'editor']]);

        $this->putJson("/api/v1/users/{$ada->getKey()}/roles", ['roles' => []])
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_roles_of_other_guards_are_kept(): void
    {
        $ada = $this->makeUser('Ada Lovelace', 'ada@example.com');
        $other = Role::create(['name' => 'api-client', 'guard_name' => 'api']);
        DB::table('model_has_roles')->insert(['role_id' => $other->getKey(), 'model_type' => ApiUser::class, 'model_id' => $ada->getKey()]);

        $this->putJson("/api/v1/users/{$ada->getKey()}/roles", ['roles' => ['editor']])->assertOk();

        $this->assertSame(2, DB::table('model_has_roles')->where('model_id', $ada->getKey())->count());
    }

    public function test_unknown_or_guest_roles_are_rejected(): void
    {
        $id = $this->manager->getKey();

        $this->putJson("/api/v1/users/{$id}/roles", ['roles' => ['admin', 'nope']])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['roles.1']]);
        $this->putJson("/api/v1/users/{$id}/roles", ['roles' => ['admin', 'guest']])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['roles.1']]);
    }

    public function test_syncing_the_last_manager_roles_away_is_refused(): void
    {
        $this->putJson("/api/v1/users/{$this->manager->getKey()}/roles", ['roles' => ['editor']])
            ->assertStatus(409)
            ->assertJsonPath('code', 'last_manager');

        $this->assertTrue($this->manager->fresh()->hasRole('admin'));
    }

    public function test_an_unknown_user_is_not_found(): void
    {
        $this->getJson('/api/v1/users/999/roles')->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    protected function makeUser(string $name, string $email): ApiUser
    {
        /** @var ApiUser */
        return ApiUser::query()->forceCreate(['name' => $name, 'email' => $email, 'password' => 'secret-hash']);
    }

    protected function countQueries(string $uri): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($uri)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
