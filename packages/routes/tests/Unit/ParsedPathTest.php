<?php

namespace KusikusiCMS\Routes\Tests\Unit;

use KusikusiCMS\Routes\Support\ParsedPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParsedPathTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function uris(): array
    {
        return [
            'plain' => ['/my-section/my-path', '/my-section/my-path', 'html'],
            'html extension' => ['/my-section/my-path.html', '/my-section/my-path', 'html'],
            'index.html' => ['/my-section/my-path/index.html', '/my-section/my-path', 'html'],
            'trailing slash' => ['/my-section/my-path/', '/my-section/my-path', 'html'],
            'rss' => ['/my-section/my-path.rss', '/my-section/my-path', 'rss'],
            'index.amp' => ['/my-section/my-path/index.amp', '/my-section/my-path', 'amp'],
            'root' => ['/', '/', 'html'],
            'empty' => ['', '/', 'html'],
            'root index.html' => ['/index.html', '/', 'html'],
            'root index.rss' => ['/index.rss', '/', 'rss'],
            'uppercase extension' => ['/page.RSS', '/page', 'rss'],
            'double slashes' => ['//my-section//my-path', '/my-section/my-path', 'html'],
            'query string' => ['/page.rss?x=1', '/page', 'rss'],
            'encoded' => ['/secci%C3%B3n', '/sección', 'html'],
            'dot in a parent segment' => ['/v1.2/page', '/v1.2/page', 'html'],
            'hidden-like segment' => ['/.well', '/.well', 'html'],
        ];
    }

    #[DataProvider('uris')]
    public function test_it_parses_the_path_and_format(string $uri, string $path, string $format): void
    {
        $parsed = ParsedPath::fromUri($uri);

        $this->assertSame($path, $parsed->path);
        $this->assertSame($format, $parsed->format);
    }

    public function test_it_uses_the_given_default_format(): void
    {
        $this->assertSame('json', ParsedPath::fromUri('/page', 'json')->format);
    }

    public function test_it_builds_uris_keeping_non_default_formats(): void
    {
        $this->assertSame('/page', ParsedPath::toUri('/page', 'html'));
        $this->assertSame('/page.rss', ParsedPath::toUri('/page', 'rss'));
        $this->assertSame('/', ParsedPath::toUri('/', 'html'));
        $this->assertSame('/index.rss', ParsedPath::toUri('/', 'rss'));
    }
}
