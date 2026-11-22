<?php

namespace KusikusiCMS\Api\Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use KusikusiCMS\Api\Api;
use KusikusiCMS\Api\Support\Capabilities;
use KusikusiCMS\Api\Support\Pagination;
use KusikusiCMS\Api\Tests\Fixtures\User;
use KusikusiCMS\Api\Tests\TestCase;
use KusikusiCMS\Models\Entity;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PlumbingTest extends TestCase
{
    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        Api::routes(function () {
            Route::get('test/forbidden', fn () => throw new AuthorizationException);
            Route::get('test/validation', fn () => throw ValidationException::withMessages(['title' => 'Required.']));
            Route::get('test/missing', fn () => throw new NotFoundHttpException);
            Route::get('test/boom', fn () => throw new RuntimeException('secret detail'));
        });

        $router->get('outside/boom', fn () => throw new RuntimeException('outside'));
    }

    public function test_api_routes_use_the_configured_prefix_and_name(): void
    {
        $this->assertTrue(Route::has('kusikusicms.api.auth.login'));
        $this->assertSame('/api/v1/me', route('kusikusicms.api.me', absolute: false));
    }

    public function test_errors_use_the_envelope(): void
    {
        $this->getJson('/api/v1/test/forbidden')->assertForbidden()
            ->assertExactJson(['message' => 'This action is unauthorized.', 'code' => 'forbidden']);

        $this->getJson('/api/v1/test/validation')->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('errors.title.0', 'Required.');

        $this->getJson('/api/v1/test/missing')->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->getJson('/api/v1/does-not-exist')->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_server_errors_hide_details_without_debug(): void
    {
        config(['app.debug' => false]);

        $this->get('/api/v1/test/boom')->assertStatus(500)
            ->assertExactJson(['message' => 'Server error.', 'code' => 'server_error']);
    }

    public function test_api_errors_are_json_even_without_accept_header(): void
    {
        $this->get('/api/v1/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }

    public function test_non_api_routes_keep_the_default_rendering(): void
    {
        $response = $this->getJson('/outside/boom')->assertStatus(500);

        $this->assertNull($response->json('code'));
    }

    public function test_unhandled_entity_writes_are_denied_and_reads_allowed_by_default(): void
    {
        $user = User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']);
        $entity = new Entity;

        $this->assertTrue(Gate::forUser($user)->allows('view', $entity));
        $this->assertTrue(Gate::forUser($user)->allows('viewAny', Entity::class));
        $this->assertFalse(Gate::forUser($user)->allows('update', $entity));
        $this->assertFalse(Gate::forUser($user)->allows('kusikusicms.something.create', $entity));
    }

    public function test_unhandled_behaviour_is_configurable(): void
    {
        config(['kusikusicms.api.authorization.unhandled' => ['read' => 'deny', 'write' => 'allow']]);
        $user = User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']);

        $this->assertFalse(Gate::forUser($user)->allows('view', new Entity));
        $this->assertTrue(Gate::forUser($user)->allows('update', new Entity));
    }

    public function test_app_abilities_are_not_affected(): void
    {
        $user = User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']);

        $this->assertFalse(Gate::forUser($user)->allows('app-ability'));

        Gate::define('app-write', fn () => null);
        $this->assertFalse(Gate::forUser($user)->allows('app-write'));
    }

    public function test_handlers_win_over_the_default(): void
    {
        $user = User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']);
        Gate::define('kusikusicms.test.write', fn () => true);

        $this->assertTrue(Gate::forUser($user)->allows('kusikusicms.test.write'));
    }

    public function test_per_page_is_clamped(): void
    {
        $this->assertSame(25, Pagination::perPage(Request::create('/x')));
        $this->assertSame(10, Pagination::perPage(Request::create('/x', 'GET', ['per_page' => 10])));
        $this->assertSame(100, Pagination::perPage(Request::create('/x', 'GET', ['per_page' => 1000])));
        $this->assertSame(25, Pagination::perPage(Request::create('/x', 'GET', ['per_page' => 0])));
    }

    public function test_capabilities_registry_is_a_singleton(): void
    {
        $this->assertSame($this->app->make(Capabilities::class), $this->app->make(Capabilities::class));
    }
}
