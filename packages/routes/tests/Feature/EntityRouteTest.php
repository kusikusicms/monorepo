<?php

namespace KusikusiCMS\Routes\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Routes\EntityRoute;
use KusikusiCMS\Routes\Enums\RouteKind;
use KusikusiCMS\Routes\RoutesServiceProvider;
use KusikusiCMS\Routes\Tests\TestCase;

class EntityRouteTest extends TestCase
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

    public function test_migration_creates_the_entities_routes_table(): void
    {
        $this->assertTrue(Schema::hasTable('entities_routes'));
        $this->assertTrue(Schema::hasColumns('entities_routes', [
            'route_id', 'entity_id', 'path', 'entity_model', 'entity_view', 'lang', 'kind', 'created_at', 'updated_at',
        ]));
    }

    public function test_migrations_are_publishable(): void
    {
        $paths = ServiceProvider::pathsToPublish(RoutesServiceProvider::class, 'kusikusicms-routes-migrations');

        $this->assertContains(database_path('migrations'), $paths);
    }

    public function test_kind_is_cast_to_the_enum(): void
    {
        Entity::create(['id' => 'p1', 'model' => 'Page']);
        $this->makeRoute('p1', '/old', kind: RouteKind::PermanentRedirect);

        $route = EntityRoute::query()->firstOrFail();

        $this->assertSame(RouteKind::PermanentRedirect, $route->kind);
        $this->assertDatabaseHas('entities_routes', ['path' => '/old', 'kind' => 'permanent-redirect']);
    }

    public function test_find_by_path_returns_entity_id_and_lang(): void
    {
        Entity::create(['id' => 'p1', 'model' => 'Page']);
        $this->makeRoute('p1', '/section/page', 'es');

        $route = EntityRoute::findByPath('/section/page');

        $this->assertNotNull($route);
        $this->assertSame('p1', $route->entity_id);
        $this->assertSame('es', $route->lang);
        $this->assertSame(RouteKind::Primary, $route->kind);
    }

    public function test_find_by_path_normalises_the_path(): void
    {
        Entity::create(['id' => 'p1', 'model' => 'Page']);
        $this->makeRoute('p1', '/section/page');

        foreach (['section/page', '/section/page/', '//section//page', ' /section/page '] as $path) {
            $this->assertSame('p1', EntityRoute::findByPath($path)?->entity_id, "Path [{$path}] did not match");
        }
    }

    public function test_find_by_path_matches_the_root_path(): void
    {
        Entity::create(['id' => 'home', 'model' => 'Home']);

        $this->assertSame('home', EntityRoute::findByPath('/')?->entity_id);
        $this->assertSame('home', EntityRoute::findByPath('')?->entity_id);
    }

    public function test_find_by_path_returns_null_for_unknown_paths(): void
    {
        $this->assertNull(EntityRoute::findByPath('/missing'));
    }

    public function test_route_belongs_to_its_entity(): void
    {
        Entity::create(['id' => 'p1', 'model' => 'Page']);
        $route = $this->makeRoute('p1', '/page');

        $this->assertSame('p1', $route->entity?->id);
    }

    public function test_paths_are_globally_unique(): void
    {
        Entity::create(['id' => 'p1', 'model' => 'Page']);
        Entity::create(['id' => 'p2', 'model' => 'Page']);
        $this->makeRoute('p1', '/page');

        $this->expectException(QueryException::class);
        $this->makeRoute('p2', '/page', 'es');
    }

    public function test_force_deleting_an_entity_deletes_its_routes(): void
    {
        $entity = Entity::create(['id' => 'p1', 'model' => 'Page']);
        $this->makeRoute('p1', '/page');

        $entity->forceDelete();

        $this->assertDatabaseMissing('entities_routes', ['entity_id' => 'p1']);
    }

    public function test_normalize_path(): void
    {
        $this->assertSame('/', EntityRoute::normalizePath(''));
        $this->assertSame('/', EntityRoute::normalizePath('///'));
        $this->assertSame('/a/b', EntityRoute::normalizePath('a/b/'));
        $this->assertSame('/a/b', EntityRoute::normalizePath('/a//b'));
    }
}
