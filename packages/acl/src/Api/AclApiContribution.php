<?php

namespace KusikusiCMS\Acl\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Acl\Services\PermissionResolver;
use KusikusiCMS\Api\Api;
use KusikusiCMS\Api\Http\Resources\EntityResource;
use KusikusiCMS\Api\Services\EntityQuery;
use KusikusiCMS\Models\Builders\EntityBuilder;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Routes\Abilities as RouteAbilities;
use WeakMap;

/**
 * What acl adds to kusikusicms/api. Registered by AclServiceProvider only when the api package is installed
 * and `kusikusicms.acl.api.enabled` is true.
 *
 * - `can` flags on every EntityResource (view, update, delete, create_child), answered by EntityPolicy. They are
 *   computed for the whole page in `prepare`: ancestors are primed in one query and the checks run in one
 *   PermissionResolver::batch(), so the number of queries does not grow with the page size.
 * - Entity lists are filtered with whereUserCan('view').
 * - GET /me/permissions.
 * - Handlers for the routes package abilities (mapped to `update` on the entity) when routes is installed.
 */
final class AclApiContribution
{
    /** @var WeakMap<Entity, array{view: bool, update: bool, delete: bool, create_child: bool}>|null */
    private static ?WeakMap $flags = null;

    public static function register(string $version): void
    {
        EntityResource::extend(
            fn (Entity $entity, Request $request) => ['can' => self::flagsFor($entity, $request)],
            fn (Collection $entities, Request $request) => self::prepare($entities, $request),
        );

        EntityQuery::extend(function (EntityBuilder $query, Request $request, string $context): void {
            if ($context === 'index') {
                $query->whereUserCan('view', $request->user());
            }
        });

        if (class_exists(RouteAbilities::class)) {
            foreach ([RouteAbilities::CREATE_ROUTE, RouteAbilities::DELETE_ROUTE] as $ability) {
                Gate::define($ability, fn ($user, Entity $entity) => Gate::forUser($user)->allows('update', $entity));
            }
        }

        Api::routes(fn () => require __DIR__.'/../../routes/api.php');

        Api::capability('acl', $version, ['can-flags', 'filtered-lists', 'me.permissions']);
    }

    /**
     * Compute the flags of every entity of the response at once.
     *
     * @param  Collection<int, Entity>  $entities
     */
    private static function prepare(Collection $entities, Request $request): void
    {
        $resolver = app(PermissionResolver::class);
        $resolver->primeAncestors(
            $entities->pluck('id')->merge($entities->pluck('parent_entity_id'))->filter()->unique()->values()
        );

        self::$flags ??= new WeakMap;

        $resolver->batch(function () use ($entities, $request): void {
            foreach ($entities as $entity) {
                self::$flags[$entity] = self::compute($entity, $request);
            }
        });
    }

    /**
     * @return array{view: bool, update: bool, delete: bool, create_child: bool}
     */
    private static function flagsFor(Entity $entity, Request $request): array
    {
        return self::$flags[$entity] ?? self::compute($entity, $request);
    }

    /**
     * @return array{view: bool, update: bool, delete: bool, create_child: bool}
     */
    private static function compute(Entity $entity, Request $request): array
    {
        $gate = Gate::forUser($request->user());

        return [
            'view' => $gate->allows('view', $entity),
            'update' => $gate->allows('update', $entity),
            'delete' => $gate->allows('delete', $entity),
            'create_child' => $gate->allows('create', [Entity::class, $entity]),
        ];
    }
}
