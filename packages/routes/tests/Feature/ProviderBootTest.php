<?php

namespace KusikusiCMS\Routes\Tests\Feature;

use Illuminate\Support\ServiceProvider;
use KusikusiCMS\Routes\RoutesServiceProvider;
use KusikusiCMS\Routes\Tests\TestCase;

class ProviderBootTest extends TestCase
{
    public function test_provider_merges_config_under_kusikusicms_routes(): void
    {
        $this->assertSame(['*'], config('kusikusicms.routes.models'));
        $this->assertSame('slug', config('kusikusicms.routes.slug_field'));
        $this->assertSame('html', config('kusikusicms.routes.default_format'));
        $this->assertTrue(config('kusikusicms.routes.catch_all.enabled'));
    }

    public function test_default_domain_has_root_entity_and_language(): void
    {
        $domains = config('kusikusicms.routes.domains');

        $this->assertCount(1, $domains);
        $site = reset($domains);
        $this->assertSame('home', $site['root_entity_id']);
        $this->assertSame('en', $site['lang']);
    }

    public function test_config_is_publishable_with_shared_and_package_tags(): void
    {
        foreach (['kusikusicms-config', 'kusikusicms-routes-config'] as $tag) {
            $paths = ServiceProvider::pathsToPublish(RoutesServiceProvider::class, $tag);
            $this->assertContains(config_path('kusikusicms/routes.php'), $paths);
        }
    }
}
