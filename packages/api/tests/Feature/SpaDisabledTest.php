<?php

namespace KusikusiCMS\Api\Tests\Feature;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use KusikusiCMS\Api\Tests\TestCase;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class SpaDisabledTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('kusikusicms.api.auth.spa.enabled', false);
        $app['config']->set('session.driver', 'array');
        $app['config']->set('sanctum.stateful', ['localhost']);
    }

    public function test_session_login_is_not_registered(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost'])
            ->postJson('/api/v1/auth/session', ['email' => 'a@example.com', 'password' => 'x'])
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
    }

    public function test_the_stateful_middleware_is_not_added_and_no_session_cookie_is_set(): void
    {
        $route = Route::getRoutes()->getByName('kusikusicms.api.me');
        $this->assertNotContains(EnsureFrontendRequestsAreStateful::class, $route->gatherMiddleware());

        $response = $this->withHeaders(['Origin' => 'http://localhost'])->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertNull($response->getCookie((string) config('session.cookie')));
    }
}
