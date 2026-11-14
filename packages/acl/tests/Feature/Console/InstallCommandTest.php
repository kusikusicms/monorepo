<?php

namespace KusikusiCMS\Acl\Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use KusikusiCMS\Acl\Tests\Fixtures\User;
use KusikusiCMS\Acl\Tests\Fixtures\UserWithoutRoles;
use KusikusiCMS\Acl\Tests\TestCase;
use Spatie\Permission\Models\Role;

class InstallCommandTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    public function test_non_interactive_install_uses_defaults(): void
    {
        $this->artisan('k:acl:install', ['--no-interaction' => true])->assertSuccessful();

        $permissionConfig = (string) File::get(config_path('permission.php'));
        $this->assertStringContainsString("'enable_wildcard_permission' => true,", $permissionConfig);
        $this->assertCount(1, $this->migrations());

        $aclConfig = require config_path('kusikusicms/acl.php');
        $this->assertSame('web', $aclConfig['guard_name']);
        $this->assertSame('guest', $aclConfig['guest_role']);
        $this->assertFalse($aclConfig['guard']['enabled']);

        $guest = Role::findByName('guest', 'web');
        $this->assertSame(['entities.view.*.*'], $guest->permissions->pluck('name')->all());
    }

    public function test_options_configure_guard_ids_guest_role_and_event_guard(): void
    {
        $this->artisan('kusikusicms:acl:install', [
            '--guard' => 'api',
            '--user-id-type' => 'uuid',
            '--guest-role' => 'visitor',
            '--guest-permissions' => 'entities.view.home.self,descendants|entities.view.about.self',
            '--event-guard' => 'yes',
            '--no-interaction' => true,
        ])->assertSuccessful();

        $migration = (string) File::get($this->migrations()[0]);
        $this->assertStringContainsString("uuid(\$columnNames['model_morph_key'])", $migration);
        $this->assertStringNotContainsString("unsignedBigInteger(\$columnNames['model_morph_key'])", $migration);

        $aclConfig = (string) File::get(config_path('kusikusicms/acl.php'));
        $this->assertStringContainsString("'guard_name' => 'api',", $aclConfig);
        $this->assertStringContainsString("'guest_role' => 'visitor',", $aclConfig);
        $this->assertStringContainsString("env('KUSIKUSICMS_ACL_GUARD', true)", $aclConfig);

        $this->assertEqualsCanonicalizing(
            ['entities.view.home.self,descendants', 'entities.view.about.self'],
            Role::findByName('visitor', 'api')->permissions->pluck('name')->all(),
        );
    }

    public function test_ulid_ids_and_disabled_guest_role(): void
    {
        $this->artisan('k:acl:install', [
            '--user-id-type' => 'ulid',
            '--guest-role' => 'none',
            '--no-interaction' => true,
        ])->assertSuccessful();

        $this->assertStringContainsString("ulid(\$columnNames['model_morph_key'])", (string) File::get($this->migrations()[0]));
        $this->assertStringContainsString("'guest_role' => null,", (string) File::get(config_path('kusikusicms/acl.php')));
        $this->assertSame(0, Role::query()->count());
    }

    public function test_install_is_idempotent(): void
    {
        $this->artisan('k:acl:install', ['--user-id-type' => 'uuid', '--no-interaction' => true])->assertSuccessful();
        $migration = (string) File::get($this->migrations()[0]);

        $this->artisan('k:acl:install', ['--user-id-type' => 'uuid', '--no-interaction' => true])
            ->expectsOutputToContain('Skipped')
            ->assertSuccessful();

        $this->assertCount(1, $this->migrations());
        $this->assertSame($migration, (string) File::get($this->migrations()[0]));
        $this->assertSame(1, Role::query()->where('name', 'guest')->count());
        $this->assertCount(1, Role::findByName('guest', 'web')->permissions);
    }

    public function test_interactive_install_asks_every_question(): void
    {
        $this->artisan('k:acl:install')
            ->expectsQuestion('Which auth guard do the permissions use?', 'web')
            ->expectsChoice('Which type are the ids of your users?', 'int', ['int', 'Integer (auto increment)', 'uuid', 'UUID', 'ulid', 'ULID'])
            ->expectsConfirmation('Create a guest role whose permissions apply to everyone (visitors and users)?', 'yes')
            ->expectsQuestion('What is the name of the guest role?', 'guest')
            ->expectsQuestion('Which permissions does the guest role have? (separated by "|")', 'entities.view')
            ->expectsConfirmation('Enable the event guard? It blocks unauthorized entity writes during HTTP requests.', 'no')
            ->assertSuccessful();

        $this->assertSame(['entities.view'], Role::findByName('guest', 'web')->permissions->pluck('name')->all());
    }

    public function test_invalid_options_fail(): void
    {
        $this->artisan('k:acl:install', ['--user-id-type' => 'string', '--no-interaction' => true])->assertFailed();
        $this->artisan('k:acl:install', ['--event-guard' => 'maybe', '--no-interaction' => true])->assertFailed();
        $this->artisan('k:acl:install', ['--guest-permissions' => 'entities.view..self', '--no-interaction' => true])->assertFailed();
    }

    public function test_it_warns_when_the_user_model_lacks_has_roles(): void
    {
        config(['auth.providers.users.model' => UserWithoutRoles::class]);

        $this->artisan('k:acl:install', ['--no-interaction' => true])
            ->expectsOutputToContain('HasRoles')
            ->assertSuccessful();
    }

    /**
     * @return list<string>
     */
    private function migrations(): array
    {
        return File::glob(database_path('migrations/*_create_permission_tables.php'));
    }

    private function cleanUp(): void
    {
        File::delete([config_path('permission.php'), config_path('kusikusicms/acl.php'), ...$this->migrations()]);
    }
}
