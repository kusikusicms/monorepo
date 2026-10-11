<?php

namespace KusikusiCMS\Website\Tests\Feature;

use KusikusiCMS\Website\Tests\TestCase;

class ProviderBootTest extends TestCase
{
    public function test_provider_boots_and_merges_config(): void
    {
        $this->assertTrue(config('kusikusicms.website.routes.enabled'));
    }

    public function test_health_route_is_registered_by_default(): void
    {
        $this->getJson('/kusikusicms-health')->assertOk()->assertJson(['package' => 'kusikusicms/website']);
    }
}
