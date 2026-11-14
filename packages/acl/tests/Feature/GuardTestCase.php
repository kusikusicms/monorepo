<?php

namespace KusikusiCMS\Acl\Tests\Feature;

use KusikusiCMS\Acl\Tests\TestCase;

abstract class GuardTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('kusikusicms.acl.guard.enabled', true);
    }
}
