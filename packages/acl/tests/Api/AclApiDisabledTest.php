<?php

namespace KusikusiCMS\Acl\Tests\Api;

use Illuminate\Support\Facades\Route;
use KusikusiCMS\Models\Entity;
use Laravel\Sanctum\Sanctum;

class AclApiDisabledTest extends ApiTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('kusikusicms.acl.api.enabled', false);
    }

    public function test_the_contribution_is_not_registered(): void
    {
        Entity::query()->create(['id' => 'root', 'model' => 'home']);
        Sanctum::actingAs($this->userWith('entities.view'));

        $this->assertFalse(Route::has('kusikusicms.api.me.permissions'));
        $this->getJson('/api/v1/entities/root')->assertOk()->assertJsonMissingPath('data.can');
        $this->getJson('/api/v1/capabilities')->assertJsonCount(1, 'data.packages');
    }
}
