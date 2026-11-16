<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/entities')->assertRedirect(route('login'));
    }

    public function test_the_admin_gets_a_successful_response(): void
    {
        $admin = User::query()->where('name', 'admin')->firstOrFail();

        $this->actingAs($admin)->get('/entities')->assertStatus(200);
    }
}
