<?php

namespace KusikusiCMS\Routes\Tests\Feature;

use Illuminate\Support\Facades\Config;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Routes\EntityRoute;
use KusikusiCMS\Routes\Enums\RouteKind;
use KusikusiCMS\Routes\Tests\TestCase;
use LogicException;

class EntityQueryRoutesTest extends TestCase
{
    private function makeRoute(string $entityId, string $path, string $lang = 'en', RouteKind $kind = RouteKind::Primary): EntityRoute
    {
        return EntityRoute::create([
            'entity_id' => $entityId,
            'path' => $path,
            'entity_model' => 'Page',
            'entity_view' => 'page',
            'lang' => $lang,
            'kind' => $kind,
        ]);
    }

    private function entity(string $id, ?string $parent = null, string $model = 'Page'): Entity
    {
        return Entity::create(['id' => $id, 'parent_entity_id' => $parent, 'model' => $model]);
    }

    public function test_with_route_adds_the_primary_path_in_the_default_language(): void
    {
        Config::set('kusikusicms.models.default_language', 'en');
        $this->entity('p1');
        $this->makeRoute('p1', '/page-en', 'en');
        $this->makeRoute('p1', '/pagina-es', 'es');

        $entity = Entity::query()->withRoute()->findOrFail('p1');

        $this->assertSame('/page-en', $entity->route);
    }

    public function test_with_route_uses_the_given_language(): void
    {
        $this->entity('p1');
        $this->makeRoute('p1', '/page-en', 'en');
        $this->makeRoute('p1', '/pagina-es', 'es');

        $this->assertSame('/pagina-es', Entity::query()->withRoute('es')->findOrFail('p1')->route);
    }

    public function test_with_route_accepts_an_options_array(): void
    {
        $this->entity('p1');
        $this->makeRoute('p1', '/pagina-es', 'es');

        $this->assertSame('/pagina-es', Entity::query()->withRoute(['lang' => 'es'])->findOrFail('p1')->route);
    }

    public function test_with_route_does_not_change_the_selected_columns(): void
    {
        $this->entity('home');
        $this->entity('s1', 'home');
        $this->makeRoute('s1', '/one', 'en');

        foreach ([
            Entity::query()->withRoute('en')->childrenOf('home'),
            Entity::query()->childrenOf('home')->withRoute('en'),
        ] as $query) {
            $child = $query->get()->first();
            $this->assertSame('/one', $child->route);
            $this->assertArrayNotHasKey('publish_at', $child->getAttributes());
        }
    }

    public function test_with_route_is_null_when_the_entity_has_no_route(): void
    {
        $this->entity('p1');
        $this->makeRoute('p1', '/page-es', 'es');

        $entity = Entity::query()->withRoute('en')->findOrFail('p1');

        $this->assertNull($entity->route);
    }

    public function test_with_route_ignores_alias_and_redirect_routes(): void
    {
        $this->entity('p1');
        $this->makeRoute('p1', '/old-page', 'en', RouteKind::PermanentRedirect);
        $this->makeRoute('p1', '/alias-page', 'en', RouteKind::Alias);

        $this->assertNull(Entity::query()->withRoute('en')->findOrFail('p1')->route);
    }

    public function test_with_route_is_included_in_to_array(): void
    {
        $this->entity('p1');
        $this->makeRoute('p1', '/page-en', 'en');

        $array = Entity::query()->withRoute()->findOrFail('p1')->toArray();

        $this->assertArrayHasKey('route', $array);
        $this->assertSame('/page-en', $array['route']);
    }

    public function test_with_route_chains_with_filters(): void
    {
        $this->entity('home');
        $this->entity('s1', 'home', 'Section');
        $this->entity('s2', 'home', 'Section');
        $this->makeRoute('s1', '/section-one', 'en');
        $this->makeRoute('s2', '/section-two', 'en');

        $children = Entity::query()
            ->withRoute('en')
            ->childrenOf('home')
            ->where('model', 'Section')
            ->orderBy('id')
            ->get();

        $this->assertSame(['s1', 's2'], $children->pluck('id')->all());
        $this->assertSame(['/section-one', '/section-two'], $children->pluck('route')->all());
    }

    public function test_with_route_does_not_make_the_entity_dirty_and_save_works(): void
    {
        $this->entity('p1');
        $this->makeRoute('p1', '/page-en', 'en');

        $entity = Entity::query()->withRoute('en')->findOrFail('p1');

        $this->assertFalse($entity->isDirty('route'));
        $this->assertFalse($entity->isDirty());
        $this->assertTrue($entity->save());
    }

    public function test_routes_relationship_can_be_eager_loaded(): void
    {
        $this->entity('p1');
        $this->entity('p2');
        $this->makeRoute('p1', '/one', 'en');
        $this->makeRoute('p1', '/uno', 'es');
        $this->makeRoute('p1', '/one-alias', 'en', RouteKind::Alias);
        $this->makeRoute('p2', '/two', 'en');

        $entities = Entity::query()->with('routes')->orderBy('id')->get();

        $this->assertEqualsCanonicalizing(['/one', '/uno', '/one-alias'], $entities[0]->routes->pluck('path')->all());
        $this->assertSame(['/two'], $entities[1]->routes->pluck('path')->all());
        $this->assertSame(RouteKind::Alias, $entities[0]->routes->firstWhere('path', '/one-alias')->kind);
    }

    public function test_routes_relationship_can_be_constrained(): void
    {
        $this->entity('p1');
        $this->makeRoute('p1', '/one', 'en');
        $this->makeRoute('p1', '/uno', 'es');

        $entity = Entity::query()->with(['routes' => fn ($q) => $q->where('lang', 'es')])->findOrFail('p1');

        $this->assertSame(['/uno'], $entity->routes->pluck('path')->all());
    }

    public function test_routes_relationship_works_lazily_and_on_entity_subclasses(): void
    {
        $this->entity('p1');
        $this->makeRoute('p1', '/one', 'en');

        $sub = new class extends Entity {};
        $found = $sub::query()->with('routes')->findOrFail('p1');

        $this->assertSame(['/one'], Entity::query()->findOrFail('p1')->routes->pluck('path')->all());
        $this->assertSame(['/one'], $found->routes->pluck('path')->all());
    }

    public function test_routes_relationship_does_not_make_the_entity_dirty(): void
    {
        $this->entity('p1');
        $this->makeRoute('p1', '/one', 'en');

        $entity = Entity::query()->with('routes')->findOrFail('p1');

        $this->assertFalse($entity->isDirty());
        $this->assertTrue($entity->save());
    }

    public function test_with_route_throws_on_non_entity_queries(): void
    {
        $this->expectException(LogicException::class);

        EntityRoute::query()->withRoute();
    }
}
