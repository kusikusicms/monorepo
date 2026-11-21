<?php

namespace KusikusiCMS\Api\Tests\Feature;

use Illuminate\Support\Facades\Hash;
use KusikusiCMS\Api\Tests\Fixtures\User;
use KusikusiCMS\Api\Tests\TestCase;

class TokenAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => Hash::make('secret-password'),
        ]);
    }

    public function test_login_returns_a_token_and_the_user(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'device_name' => 'phpunit',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']])
            ->assertJsonMissingPath('user.password');
    }

    public function test_the_issued_token_authenticates_me(): void
    {
        $token = $this->login();

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ada@example.com');
    }

    public function test_wrong_password_returns_invalid_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'wrong',
            'device_name' => 'phpunit',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_credentials');
    }

    public function test_unknown_email_returns_invalid_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'secret-password',
            'device_name' => 'phpunit',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_credentials');
    }

    public function test_login_validates_its_input(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['email', 'password', 'device_name']]);
    }

    public function test_the_sixth_failed_attempt_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'ada@example.com',
                'password' => 'wrong',
                'device_name' => 'phpunit',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'device_name' => 'phpunit',
        ])
            ->assertStatus(429)
            ->assertJsonPath('code', 'too_many_requests');
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $token = $this->login();

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    protected function login(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'device_name' => 'phpunit',
        ])->json('token');
    }
}
