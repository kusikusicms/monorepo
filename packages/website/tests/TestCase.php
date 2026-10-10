<?php

namespace KusikusiCMS\Website\Tests;

use KusikusiCMS\Website\WebsiteServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            WebsiteServiceProvider::class,
        ];
    }
}
