<?php

namespace KusikusiCMS\Admin\Tests;

use KusikusiCMS\Admin\AdminServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            AdminServiceProvider::class,
        ];
    }
}
