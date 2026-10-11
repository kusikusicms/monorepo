<?php

namespace KusikusiCMS\Media\Tests\Feature;

use KusikusiCMS\Media\Tests\TestCase;

class ProviderBootTest extends TestCase
{
    public function test_provider_boots_and_merges_config(): void
    {
        $this->assertTrue(config('kusikusicms.media.routes.enabled'));
    }

    public function test_health_route_is_registered_by_default(): void
    {
        $uris = collect(app('router')->getRoutes()->getRoutes())->map->uri()->all();

        $this->assertContains('media/kusikusicms-health', $uris);
    }
}
