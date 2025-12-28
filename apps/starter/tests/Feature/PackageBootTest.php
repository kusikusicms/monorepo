<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PackageBootTest extends TestCase
{
    public function test_package_providers_boot_and_config_is_available(): void
    {
        $this->assertSame('en', config('kusikusicms.models.default_language'));
    }

    public function test_about_command_includes_package_entry(): void
    {
        Artisan::call('about');
        $output = Artisan::output();

        $this->assertStringContainsString('KusikusiCMS core models package', $output);
    }
}
