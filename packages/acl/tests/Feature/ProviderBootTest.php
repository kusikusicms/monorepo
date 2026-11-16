<?php

namespace KusikusiCMS\Acl\Tests\Feature;

use Illuminate\Support\ServiceProvider;
use KusikusiCMS\Acl\AclServiceProvider;
use KusikusiCMS\Acl\Tests\TestCase;

class ProviderBootTest extends TestCase
{
    public function test_provider_merges_config_under_kusikusicms_acl(): void
    {
        $this->assertSame('entities', config('kusikusicms.acl.prefix'));
        $this->assertSame('web', config('kusikusicms.acl.guard_name'));
        $this->assertSame('guest', config('kusikusicms.acl.guest_role'));
        $this->assertFalse(config('kusikusicms.acl.guard.enabled'));
    }

    public function test_config_is_publishable_with_shared_and_package_tags(): void
    {
        foreach (['kusikusicms-config', 'kusikusicms-acl-config'] as $tag) {
            $paths = ServiceProvider::pathsToPublish(AclServiceProvider::class, $tag);
            $this->assertContains(config_path('kusikusicms/acl.php'), $paths);
        }
    }
}
