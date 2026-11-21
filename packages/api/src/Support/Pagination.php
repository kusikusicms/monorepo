<?php

namespace KusikusiCMS\Api\Support;

use Illuminate\Http\Request;

/**
 * Length-aware pagination conventions: `?per_page=` defaults to and is capped by the configured values.
 */
final class Pagination
{
    /**
     * The page size requested, clamped to 1..max_per_page.
     */
    public static function perPage(Request $request): int
    {
        $default = (int) config('kusikusicms.api.pagination.per_page', 25);
        $max = max(1, (int) config('kusikusicms.api.pagination.max_per_page', 100));
        $requested = (int) $request->query('per_page', (string) $default);

        if ($requested < 1) {
            $requested = $default;
        }

        return min(max(1, $requested), $max);
    }
}
