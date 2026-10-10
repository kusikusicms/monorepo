<?php

namespace KusikusiCMS\Media\Tests;

use KusikusiCMS\Media\MediaServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            MediaServiceProvider::class,
        ];
    }
}
