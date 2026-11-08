<?php

namespace KusikusiCMS\Routes\Support;

use KusikusiCMS\Routes\EntityRoute;

/**
 * The stored path and the format inferred from a request URI.
 *
 * - "/my-section/my-path", "/my-section/my-path/", "/my-section/my-path.html" and
 *   "/my-section/my-path/index.html" give ["/my-section/my-path", "html"].
 * - "/my-section/my-path.rss" gives "rss", "/my-section/my-path/index.amp" gives "amp".
 * - "/" and "/index.html" give ["/", "html"].
 */
final readonly class ParsedPath
{
    public function __construct(
        public string $path,
        public string $format,
    ) {}

    /**
     * Parse a request URI (or path). The query string is ignored and the path is URL-decoded.
     *
     * @param  string  $default  The format used when the URI has no extension
     */
    public static function fromUri(string $uri, string $default = 'html'): self
    {
        $path = rawurldecode(strtok($uri, '?#') ?: '');
        $segments = array_values(array_filter(explode('/', $path), fn (string $segment) => trim($segment) !== ''));
        $format = strtolower($default);

        $last = array_pop($segments);
        if ($last !== null) {
            $dot = strrpos($last, '.');
            if ($dot !== false && $dot > 0) {
                $format = strtolower(substr($last, $dot + 1));
                $last = substr($last, 0, $dot);
            }
            if ($last !== 'index') {
                $segments[] = $last;
            }
        }

        return new self(EntityRoute::normalizePath(implode('/', $segments)), $format);
    }

    /**
     * Build the URI of a path in a format: the default format has no extension, other formats
     * get one ("/page.rss", or "/index.rss" for the root).
     */
    public static function toUri(string $path, string $format, string $default = 'html'): string
    {
        $path = EntityRoute::normalizePath($path);
        if (strtolower($format) === strtolower($default)) {
            return $path;
        }

        return ($path === '/' ? '/index' : $path).'.'.$format;
    }
}
