<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use KusikusiCMS\Models\Entity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sanctum.stateful' => ['localhost']]);

        Entity::create(['id' => 'home', 'model' => 'Home'])->createContents(['title' => 'Home', 'slug' => '']);
        Entity::create(['id' => 'about', 'parent_entity_id' => 'home', 'model' => 'Section'])
            ->createContents(['title' => 'About', 'slug' => 'about']);

        $user = User::factory()->create(['email' => 'editor@example.com', 'password' => 'secret-password']);
        $user->givePermissionTo(
            Permission::findOrCreate('entities.view', 'web'),
            Permission::findOrCreate('entities.update.about.self', 'web'),
        );
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_token_login_then_list_entities_with_route_and_can_flags(): void
    {
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'editor@example.com',
            'password' => 'secret-password',
            'device_name' => 'sandbox-test',
        ])->assertCreated()->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/entities?sort=id&lang=en')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'about')
            ->assertJsonPath('data.0.route', '/about')
            ->assertJsonPath('data.0.can.update', true)
            ->assertJsonPath('data.1.route', '/')
            ->assertJsonPath('data.1.can.update', false);
    }

    public function test_session_login_from_the_spa_then_cookie_requests(): void
    {
        $headers = ['Origin' => 'http://localhost', 'Referer' => 'http://localhost/'];

        $this->withHeaders($headers)->get('/sanctum/csrf-cookie')->assertNoContent();

        $this->withHeaders($headers)
            ->postJson('/api/v1/auth/session', ['email' => 'editor@example.com', 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('data.email', 'editor@example.com');

        $this->withHeaders($headers)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', 'editor@example.com');
    }

    public function test_capabilities_list_the_installed_packages(): void
    {
        $names = collect($this->getJson('/api/v1/capabilities')->assertOk()->json('data.packages'))->pluck('name');

        $this->assertEqualsCanonicalizing(['api', 'acl', 'routes'], $names->all());
    }
}
