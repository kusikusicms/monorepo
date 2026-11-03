<?php

namespace KusikusiCMS\Routes\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\EntityContent;
use KusikusiCMS\Routes\EntityRoute;
use KusikusiCMS\Routes\Enums\RouteKind;
use KusikusiCMS\Routes\Exceptions\RoutePathConflictException;
use KusikusiCMS\Routes\Support\PlannedRoute;
use KusikusiCMS\Routes\Support\RoutePlan;

/**
 * Keeps the entities_routes table in sync with the entities' slugs.
 *
 * Syncing runs in two phases so a conflict can abort a write before anything is persisted:
 * - plan(): read-only, computes the primary path of the entity and all its descendants in a
 *   language and throws a RoutePathConflictException when a path is already taken.
 * - apply(): writes the plan inside a transaction (demotes old primaries to permanent
 *   redirects, promotes own redirects back, reclaims other entities' redirects).
 *
 * Bound as a singleton; apps can extend it and rebind it in the container.
 */
class RouteManager
{
    public function __construct(protected PathBuilder $paths) {}

    /**
     * Whether an entity's model gets routes ("models" contains "*" or the model, and "except_models" does not).
     */
    public function isRoutable(Entity $entity): bool
    {
        $model = (string) $entity->model;
        $models = (array) config('kusikusicms.routes.models', ['*']);
        $except = (array) config('kusikusicms.routes.except_models', []);

        if (in_array($model, $except, true)) {
            return false;
        }

        return in_array('*', $models, true) || in_array($model, $models, true);
    }

    /**
     * Whether a content write changes a slug (a new slug content, or a changed slug text).
     */
    public function isSlugChange(EntityContent $content): bool
    {
        return $content->field === $this->paths->slugField()
            && (! $content->exists || $content->isDirty('text'));
    }

    /**
     * Compute (read-only) the primary routes of an entity and its descendants in a language.
     *
     * @param  Entity|string  $entity  An entity id, or an instance (its unsaved parent_entity_id is used)
     * @param  string|null  $slugOverride  The entity's slug to use instead of the stored one
     * @param  bool  $deleted  Whether the entity's slug is being removed (it gets no path)
     *
     * @throws RoutePathConflictException
     */
    public function plan(Entity|string $entity, string $lang, ?string $slugOverride = null, bool $deleted = false): RoutePlan
    {
        $entity = $this->resolveEntity($entity);
        if ($entity === null) {
            return new RoutePlan($lang);
        }

        $path = $deleted ? null : $this->paths->build($entity, $lang, $slugOverride);
        // Entities outside the root tree are ignored, unless they still own routes (e.g. moved out of the tree)
        if ($path === null && ! $this->paths->inScope($entity) && ! $this->hasRoutes((string) $entity->id, $lang)) {
            return new RoutePlan($lang);
        }

        $routes = [];
        if ($this->isRoutable($entity)) {
            $routes[] = $this->plannedRoute($entity, $lang, $path);
        }

        // Cascade down the tree, one query for children and one for their slugs per level
        $level = [(string) $entity->id => $path];
        $visited = [(string) $entity->id => true];
        while ($level !== []) {
            $children = Entity::withTrashed()
                ->whereIn('parent_entity_id', array_map('strval', array_keys($level)))
                ->get(['id', 'parent_entity_id', 'model', 'view']);
            if ($children->isEmpty()) {
                break;
            }
            $slugs = $this->paths->slugsFor($children->map(fn (Entity $child) => (string) $child->id)->values()->all(), $lang);
            $next = [];
            foreach ($children as $child) {
                $childId = (string) $child->id;
                if (isset($visited[$childId])) {
                    continue;
                }
                $visited[$childId] = true;
                $childPath = $this->paths->join(
                    $level[(string) $child->parent_entity_id] ?? null,
                    $this->paths->sanitize($slugs[$childId] ?? null),
                );
                if ($this->isRoutable($child)) {
                    $routes[] = $this->plannedRoute($child, $lang, $childPath);
                }
                $next[$childId] = $childPath;
            }
            $level = $next;
        }

        $this->assertNoConflicts($routes, $lang);

        return new RoutePlan($lang, $routes);
    }

    /**
     * Write a plan built by plan(), inside a transaction.
     */
    public function apply(RoutePlan $plan): void
    {
        if ($plan->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($plan) {
            foreach ($plan->routes as $planned) {
                $current = $this->primaryRoute($planned->entityId, $planned->lang);
                if ($planned->path === null) {
                    $current?->delete();

                    continue;
                }
                if ($current !== null && $current->path === $planned->path) {
                    $current->fill(['entity_model' => $planned->entityModel, 'entity_view' => $planned->entityView])->save();

                    continue;
                }
                $this->put($planned->entityId, $planned->lang, $planned->path, RouteKind::Primary, $planned->entityModel, $planned->entityView);
            }
        });
    }

    /**
     * Re-sync the routes of an entity and its descendants, in one language or in every language
     * where the entity has a slug or a route. All languages are validated before anything is written.
     *
     * @throws RoutePathConflictException
     */
    public function syncEntity(Entity|string $entity, ?string $lang = null): void
    {
        $entity = $this->resolveEntity($entity);
        if ($entity === null) {
            return;
        }

        $plans = array_map(fn (string $language) => $this->plan($entity, $language), $this->langsOf($entity, $lang));

        DB::transaction(function () use ($plans) {
            foreach ($plans as $plan) {
                $this->apply($plan);
            }
        });
    }

    /**
     * Validate (read-only) that the entity and its descendants can be re-pathed, in every language.
     * Used before saving a parent_entity_id change.
     *
     * @throws RoutePathConflictException
     */
    public function validateEntity(Entity $entity, ?string $lang = null): void
    {
        foreach ($this->langsOf($entity, $lang) as $language) {
            $this->plan($entity, $language);
        }
    }

    /**
     * Manually create (or convert) a route of an entity, denormalising its model and view.
     * A new primary route demotes the entity's current primary in that language to a permanent redirect.
     *
     * @throws RoutePathConflictException When the path is used by another entity's primary or alias, or in another language
     */
    public function createRoute(string $entityId, string $lang, string $path, RouteKind $kind = RouteKind::Primary): EntityRoute
    {
        $entity = Entity::withTrashed()->findOrFail($entityId);
        $path = EntityRoute::normalizePath($path);

        return DB::transaction(function () use ($entity, $lang, $path, $kind) {
            $existing = EntityRoute::query()->where('path', $path)->first();
            if ($existing !== null && ! $this->canClaim($existing, (string) $entity->id, $lang)) {
                throw new RoutePathConflictException($path, $existing->entity_id, $existing->lang);
            }

            return $this->put((string) $entity->id, $lang, $path, $kind, (string) $entity->model, $this->viewOf($entity));
        });
    }

    /**
     * Update the denormalised entity_model and entity_view columns of all the entity's routes.
     */
    public function refreshDenormalized(Entity $entity): void
    {
        EntityRoute::query()
            ->where('entity_id', $entity->id)
            ->update(['entity_model' => (string) $entity->model, 'entity_view' => $this->viewOf($entity)]);
    }

    /**
     * Store a route row at a path, reclaiming the path from another owner (already validated).
     */
    protected function put(string $entityId, string $lang, string $path, RouteKind $kind, string $entityModel, ?string $entityView): EntityRoute
    {
        $existing = EntityRoute::query()->where('path', $path)->first();
        if ($existing !== null && ($existing->entity_id !== $entityId || $existing->lang !== $lang)) {
            $existing->delete();
            $existing = null;
        }

        if ($kind === RouteKind::Primary) {
            EntityRoute::query()
                ->where('entity_id', $entityId)
                ->where('lang', $lang)
                ->where('kind', RouteKind::Primary->value)
                ->where('path', '!=', $path)
                ->update(['kind' => RouteKind::PermanentRedirect->value]);
        }

        $route = $existing ?? new EntityRoute(['entity_id' => $entityId, 'path' => $path, 'lang' => $lang]);
        $route->fill(['kind' => $kind, 'entity_model' => $entityModel, 'entity_view' => $entityView])->save();

        return $route;
    }

    /**
     * Throw when a planned path is taken by another planned route or by a stored route that cannot be reclaimed.
     *
     * @param  list<PlannedRoute>  $routes
     *
     * @throws RoutePathConflictException
     */
    protected function assertNoConflicts(array $routes, string $lang): void
    {
        $newPaths = [];
        foreach ($routes as $planned) {
            $newPaths[$planned->entityId] = $planned->path;
        }
        $paths = array_values(array_filter($newPaths, fn (?string $path) => $path !== null));
        if ($paths === []) {
            return;
        }

        /** @var Collection<string, EntityRoute> $stored */
        $stored = EntityRoute::query()->whereIn('path', $paths)->get()->keyBy('path');
        $claimed = [];
        foreach ($routes as $planned) {
            if ($planned->path === null) {
                continue;
            }
            if (isset($claimed[$planned->path])) {
                throw new RoutePathConflictException($planned->path, $claimed[$planned->path], $lang);
            }
            $claimed[$planned->path] = $planned->entityId;

            $existing = $stored->get($planned->path);
            if ($existing !== null && ! $this->canClaim($existing, $planned->entityId, $lang, $newPaths)) {
                throw new RoutePathConflictException($planned->path, $existing->entity_id, $existing->lang);
            }
        }
    }

    /**
     * Whether an entity can take the path of a stored route: it owns it in the same language, the stored
     * route is a redirect, or it is the primary of a planned entity that moves to another path.
     *
     * @param  array<string, string|null>  $newPaths  Planned paths (same language) keyed by entity id
     */
    protected function canClaim(EntityRoute $existing, string $entityId, string $lang, array $newPaths = []): bool
    {
        if ($existing->entity_id === $entityId && $existing->lang === $lang) {
            return true;
        }
        if ($existing->kind->isRedirect()) {
            return true;
        }

        return $existing->kind === RouteKind::Primary
            && $existing->lang === $lang
            && array_key_exists($existing->entity_id, $newPaths)
            && $newPaths[$existing->entity_id] !== $existing->path;
    }

    /**
     * The languages to sync: the given one, or every language where the entity has a slug or a route.
     *
     * @return list<string>
     */
    protected function langsOf(Entity $entity, ?string $lang): array
    {
        if ($lang !== null) {
            return [$lang];
        }

        $fromContents = EntityContent::query()
            ->where('entity_id', $entity->id)
            ->where('field', $this->paths->slugField())
            ->pluck('lang');
        $fromRoutes = EntityRoute::query()->where('entity_id', $entity->id)->pluck('lang');

        return $fromContents->merge($fromRoutes)->map(fn ($value) => (string) $value)->unique()->values()->all();
    }

    protected function primaryRoute(string $entityId, string $lang): ?EntityRoute
    {
        return EntityRoute::query()
            ->where('entity_id', $entityId)
            ->where('lang', $lang)
            ->where('kind', RouteKind::Primary->value)
            ->first();
    }

    protected function hasRoutes(string $entityId, string $lang): bool
    {
        return EntityRoute::query()->where('entity_id', $entityId)->where('lang', $lang)->exists();
    }

    protected function plannedRoute(Entity $entity, string $lang, ?string $path): PlannedRoute
    {
        return new PlannedRoute((string) $entity->id, $lang, $path, (string) $entity->model, $this->viewOf($entity));
    }

    protected function viewOf(Entity $entity): ?string
    {
        $view = $entity->getAttribute('view');

        return $view === null ? null : (string) $view;
    }

    protected function resolveEntity(Entity|string $entity): ?Entity
    {
        if ($entity instanceof Entity) {
            return $entity;
        }

        $found = Entity::withTrashed()->find($entity);

        return $found instanceof Entity ? $found : null;
    }
}
