<?php

namespace KusikusiCMS\Admin\Tests\Feature;

use KusikusiCMS\Admin\Tests\TestCase;

class RoutesDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('kusikusicms.admin.routes.enabled', false);
    }

    public function test_routes_are_not_registered_when_disabled(): void
    {
        $this->assertFalse(app('router')->has('kusikusicms.admin.dashboard'));
    }
}
