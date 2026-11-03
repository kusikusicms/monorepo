<?php

namespace KusikusiCMS\Routes\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use KusikusiCMS\Models\ModelsServiceProvider;
use KusikusiCMS\Routes\RoutesServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            ModelsServiceProvider::class,
            RoutesServiceProvider::class,
        ];
    }
}
