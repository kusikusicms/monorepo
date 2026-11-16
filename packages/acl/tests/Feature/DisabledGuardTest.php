<?php

namespace KusikusiCMS\Acl\Tests\Feature;

use Illuminate\Foundation\Application;
use KusikusiCMS\Acl\Tests\TestCase;
use KusikusiCMS\Models\Entity;

class DisabledGuardTest extends TestCase
{
    public function test_disabled_guard_does_not_block_http_writes(): void
    {
        $runningInConsole = new \ReflectionProperty(Application::class, 'isRunningInConsole');
        $runningInConsole->setValue($this->app, false);

        $entity = Entity::create(['id' => 'unguarded']);

        $this->assertSame('unguarded', $entity->id);
    }
}
