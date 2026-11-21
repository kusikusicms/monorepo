<?php

namespace KusikusiCMS\Api\Tests\Feature;

use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use KusikusiCMS\Api\Api;
use KusikusiCMS\Api\Tests\Fixtures\User;
use KusikusiCMS\Api\Tests\TestCase;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class SessionAuthTest extends TestCase
{
    protected const ORIGIN = ['Origin' => 'http://localhost', 'Referer' => 'http://localhost/'];

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('session.driver', 'array');
        $app['config']->set('sanctum.stateful', ['localhost']);
    }

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        Api::routes(function () {
            Route::post('test/csrf', fn () => throw new TokenMismatchException);
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => Hash::make('secret-password'),
        ]);
    }

    public function test_the_api_group_runs_the_stateful_middleware(): void
    {
        $route = Route::getRoutes()->getByName('kusikusicms.api.me');

        $this->assertContains(EnsureFrontendRequestsAreStateful::class, $route->gatherMiddleware());
    }

    public function test_the_csrf_cookie_endpoint_sets_the_xsrf_cookie(): void
    {
        $this->withHeaders(self::ORIGIN)->get('/sanctum/csrf-cookie')
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN');
    }

    public function test_session_login_then_cookie_only_requests_are_authenticated(): void
    {
        $before = $this->withHeaders(self::ORIGIN)->get('/sanctum/csrf-cookie')->getCookie($this->cookieName())?->getValue();

        $login = $this->sessionLogin($before);
        $login->assertOk()
            ->assertJsonPath('data.email', 'ada@example.com')
            ->assertJsonMissingPath('token');

        $after = $this->sessionCookie($login);
        $this->assertNotNull($after);
        $this->assertNotSame($before, $after, 'The session id is regenerated on login.');

        $this->app['auth']->forgetGuards();

        $this->withHeaders(self::ORIGIN)
            ->withCookie($this->cookieName(), $after)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ada@example.com');
    }

    public function test_logout_ends_the_session(): void
    {
        $cookie = $this->sessionCookie($this->sessionLogin()->assertOk());
        $this->app['auth']->forgetGuards();

        $this->withHeaders(self::ORIGIN)
            ->withCookie($this->cookieName(), $cookie)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->app['auth']->forgetGuards();

        $this->withHeaders(self::ORIGIN)
            ->withCookie($this->cookieName(), $cookie)
            ->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_session_login_shares_the_errors_and_throttle_with_token_login(): void
    {
        $this->sessionLogin(password: 'wrong')->assertUnprocessable()->assertJsonPath('code', 'invalid_credentials');

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'ada@example.com',
                'password' => 'wrong',
                'device_name' => 'phpunit',
            ])->assertUnprocessable();
        }

        $this->sessionLogin()->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
    }

    public function test_session_login_from_a_non_stateful_origin_is_rejected(): void
    {
        $response = $this->withHeaders(['Origin' => 'https://evil.example'])
            ->postJson('/api/v1/auth/session', ['email' => 'ada@example.com', 'password' => 'secret-password']);

        $response->assertUnprocessable()->assertJsonPath('code', 'stateful_required');
        $this->assertNull($response->getCookie($this->cookieName()));
    }

    public function test_bearer_tokens_keep_working_from_any_origin(): void
    {
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'device_name' => 'phpunit',
        ])->json('token');

        $this->withHeaders(['Origin' => 'https://mobile.example'])
            ->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk();
    }

    public function test_csrf_mismatch_uses_the_envelope(): void
    {
        $this->postJson('/api/v1/test/csrf')
            ->assertStatus(419)
            ->assertExactJson(['message' => 'CSRF token mismatch.', 'code' => 'csrf_mismatch']);
    }

    protected function sessionLogin(?string $cookie = null, string $password = 'secret-password'): TestResponse
    {
        $request = $this->withHeaders(self::ORIGIN);

        if ($cookie !== null) {
            $request = $request->withCookie($this->cookieName(), $cookie);
        }

        return $request->postJson('/api/v1/auth/session', [
            'email' => 'ada@example.com',
            'password' => $password,
        ]);
    }

    protected function sessionCookie(TestResponse $response): ?string
    {
        return $response->getCookie($this->cookieName())?->getValue();
    }

    protected function cookieName(): string
    {
        return (string) config('session.cookie');
    }
}
