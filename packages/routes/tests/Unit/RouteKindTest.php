<?php

namespace KusikusiCMS\Routes\Tests\Unit;

use KusikusiCMS\Routes\Enums\RouteKind;
use PHPUnit\Framework\TestCase;

class RouteKindTest extends TestCase
{
    public function test_values_match_the_stored_strings(): void
    {
        $this->assertSame(
            ['primary', 'alias', 'permanent-redirect', 'temporary-redirect'],
            array_map(fn (RouteKind $kind) => $kind->value, RouteKind::cases()),
        );
    }

    public function test_only_redirect_kinds_are_redirects(): void
    {
        $this->assertFalse(RouteKind::Primary->isRedirect());
        $this->assertFalse(RouteKind::Alias->isRedirect());
        $this->assertTrue(RouteKind::PermanentRedirect->isRedirect());
        $this->assertTrue(RouteKind::TemporaryRedirect->isRedirect());
    }

    public function test_http_status_is_301_or_302_for_redirects_and_null_otherwise(): void
    {
        $this->assertNull(RouteKind::Primary->httpStatus());
        $this->assertNull(RouteKind::Alias->httpStatus());
        $this->assertSame(301, RouteKind::PermanentRedirect->httpStatus());
        $this->assertSame(302, RouteKind::TemporaryRedirect->httpStatus());
    }
}
