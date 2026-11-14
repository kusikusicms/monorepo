<?php

namespace KusikusiCMS\Acl\Tests\Unit;

use KusikusiCMS\Acl\Enums\EntityAction;
use KusikusiCMS\Acl\Enums\EntityScope;
use KusikusiCMS\Acl\Exceptions\InvalidEntityPermissionException;
use KusikusiCMS\Acl\Support\EntityPermission;
use KusikusiCMS\Acl\Tests\TestCase;

class EntityPermissionTest extends TestCase
{
    public function test_it_builds_and_parses_permission_names_with_subparts(): void
    {
        $permission = EntityPermission::make(
            [EntityAction::Create, EntityAction::Update],
            'home',
            [EntityScope::Self, EntityScope::Children],
        );

        $this->assertSame('entities.create,update.home.self,children', $permission->name());
        $this->assertSame(
            $permission->name(),
            EntityPermission::parse($permission->name())?->name(),
        );
        $this->assertTrue($permission->allows(EntityAction::Create));
        $this->assertTrue($permission->allows(EntityAction::Update));
        $this->assertFalse($permission->allows(EntityAction::Delete));
    }

    public function test_it_normalizes_missing_parts_to_wildcards(): void
    {
        $this->assertSame('entities.view.*.*', EntityPermission::parse('entities.view')?->name());
        $this->assertSame('entities.*.*.*', EntityPermission::parse('entities')?->name());
        $this->assertSame('entities.view.blog.*', EntityPermission::parse('entities.view.blog')?->name());
        $this->assertNull(EntityPermission::parse('other.view.blog.self'));
    }

    public function test_it_uses_the_configured_prefix(): void
    {
        config(['kusikusicms.acl.prefix' => 'content']);

        $permission = EntityPermission::make(EntityAction::View, 'xY1a5G', EntityScope::Self);

        $this->assertSame('content.view.xY1a5G.self', $permission->name());
        $this->assertSame('content.view.xY1a5G.self', EntityPermission::parse($permission->name())?->name());
        $this->assertNull(EntityPermission::parse('entities.view.xY1a5G.self'));
    }

    public function test_it_rejects_invalid_permission_parts(): void
    {
        $invalidInputs = [
            static fn () => EntityPermission::make('read'),
            static fn () => EntityPermission::make('view', 'an.id', 'self'),
            static fn () => EntityPermission::parse('entities.view..self'),
            static fn () => EntityPermission::parse('entities.view.blog.self.extra'),
            static fn () => EntityPermission::make('view', 'home', 'children,unknown'),
        ];

        foreach ($invalidInputs as $invalidInput) {
            try {
                $invalidInput();
                $this->fail('Invalid permission input did not throw.');
            } catch (InvalidEntityPermissionException) {
                $this->assertTrue(true);
            }
        }
    }
}
