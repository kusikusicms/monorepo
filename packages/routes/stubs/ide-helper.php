<?php

/**
 * IDE helper for the routes package. It is NOT autoloaded and never executed: it only tells PhpStorm
 * (and the Laravel IDE plugin) about the methods, attributes and relations the routes package adds at run time
 * to classes of the models package. Keep it out of the static analysis paths.
 */

namespace KusikusiCMS\Models\Builders {
    use KusikusiCMS\Routes\Builders\EntityRouteQueries;

    /**
     * @mixin EntityRouteQueries
     *
     * @method $this withRoute(string|array|null $lang = null) Add a `route` attribute with the primary path of the language.
     */
    class EntityBuilder {}
}

namespace KusikusiCMS\Models {
    use Illuminate\Database\Eloquent\Collection;
    use KusikusiCMS\Routes\EntityRoute;

    /**
     * @property-read string|null $route Primary path, loaded with withRoute().
     * @property-read Collection<int, EntityRoute> $routes All the routes, loaded with with('routes').
     *
     * @method static \KusikusiCMS\Models\Builders\EntityBuilder withRoute(string|array|null $lang = null)
     * @method \Illuminate\Database\Eloquent\Relations\HasMany<\KusikusiCMS\Routes\EntityRoute, $this> routes()
     */
    class Entity {}
}
