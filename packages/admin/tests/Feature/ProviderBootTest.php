<?php

namespace KusikusiCMS\Admin\Tests\Feature;

use KusikusiCMS\Admin\Tests\TestCase;

class ProviderBootTest extends TestCase
{
    public function test_provider_boots_and_merges_config(): void
    {
        $this->assertTrue(config('kusikusicms.admin.routes.enabled'));
    }

    public function test_dashboard_route_is_registered_by_default(): void
    {
        $this->assertTrue(app('router')->has('kusikusicms.admin.dashboard'));
    }
}
