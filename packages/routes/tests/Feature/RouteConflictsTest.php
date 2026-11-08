<?php

namespace KusikusiCMS\Routes\Tests\Feature;

use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\EntityContent;
use KusikusiCMS\Routes\EntityRoute;
use KusikusiCMS\Routes\Enums\RouteKind;
use KusikusiCMS\Routes\Exceptions\RoutePathConflictException;
use KusikusiCMS\Routes\Tests\TestCase;

class RouteConflictsTest extends TestCase
{
    private function entity(string $id, ?string $parent = null, string $model = 'Page'): Entity
    {
        return Entity::create(['id' => $id, 'parent_entity_id' => $parent, 'model' => $model]);
    }

    private function home(): void
    {
        $this->entity('home', model: 'Home')->createContents(['slug' => '']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function routesSnapshot(): array
    {
        return EntityRoute::query()->orderBy('route_id')->get()->toArray();
    }

    private function assertConflict(callable $write, string $path, string $entityId, string $lang): void
    {
        try {
            $write();
            $this->fail('RoutePathConflictException was not thrown');
        } catch (RoutePathConflictException $e) {
            $this->assertSame($path, $e->path);
            $this->assertSame($entityId, $e->conflictingEntityId);
            $this->assertSame($lang, $e->lang);
        }
    }

    public function test_another_entitys_primary_throws_and_rolls_back_create_contents(): void
    {
        $this->home();
        $this->entity('p1', 'home')->createContents(['slug' => 'page']);
        $p2 = $this->entity('p2', 'home');
        $before = $this->routesSnapshot();

        $this->assertConflict(fn () => $p2->createContents(['title' => 'Other', 'slug' => 'page']), '/page', 'p1', 'en');

        $this->assertSame(0, EntityContent::query()->where('entity_id', 'p2')->count());
        $this->assertSame($before, $this->routesSnapshot());
    }

    public function test_another_entitys_alias_throws_on_direct_content_creation(): void
    {
        $this->home();
        $this->entity('p1', 'home');
        EntityRoute::createFor('p1', 'en', '/alias', RouteKind::Alias);
        $this->entity('p2', 'home');

        $this->assertConflict(
            fn () => EntityContent::create(['entity_id' => 'p2', 'lang' => 'en', 'field' => 'slug', 'text' => 'alias']),
            '/alias', 'p1', 'en',
        );

        $this->assertSame(0, EntityContent::query()->where('entity_id', 'p2')->count());
    }

    public function test_the_same_entity_cannot_own_a_path_in_two_languages(): void
    {
        $this->home();
        $home = Entity::query()->findOrFail('home');

        $this->assertConflict(fn () => $home->createContents(['slug' => ''], 'es'), '/', 'home', 'en');

        $this->assertSame(0, EntityContent::query()->where('lang', 'es')->count());
    }

    public function test_another_entitys_redirect_is_reclaimed(): void
    {
        $this->home();
        $p1 = $this->entity('p1', 'home');
        $p1->createContents(['slug' => 'old']);
        $p1->createContents(['slug' => 'new']);

        $this->entity('p2', 'home')->createContents(['slug' => 'old']);

        $route = EntityRoute::findByPath('/old');
        $this->assertSame('p2', $route?->entity_id);
        $this->assertSame(RouteKind::Primary, $route->kind);
    }

    public function test_a_conflict_deep_in_the_cascade_rolls_back_everything(): void
    {
        $this->home();
        $this->entity('taken', 'home')->createContents(['slug' => 'news']);
        $this->entity('n1', 'taken')->createContents(['slug' => 'item']);
        $this->entity('s1', 'home', 'Section')->createContents(['slug' => 'section']);
        $this->entity('p1', 's1')->createContents(['slug' => 'item']);
        // s1 -> news clashes with taken (/news); its child p1 (/news/item) would clash with n1 as well
        $before = $this->routesSnapshot();

        $this->assertConflict(
            fn () => Entity::query()->findOrFail('s1')->createContents(['slug' => 'news']),
            '/news', 'taken', 'en',
        );

        $this->assertSame('section', EntityContent::query()->where('entity_id', 's1')->where('field', 'slug')->value('text'));
        $this->assertSame($before, $this->routesSnapshot());
    }

    public function test_a_descendant_conflict_rolls_back_the_parent_change(): void
    {
        $this->home();
        $this->entity('s1', 'home', 'Section')->createContents(['slug' => 'section']);
        $this->entity('p1', 's1')->createContents(['slug' => 'page']);
        $this->entity('x', 'home')->createContents(['slug' => 'other']);
        EntityRoute::createFor('x', 'en', '/renamed/page', RouteKind::Alias);
        $before = $this->routesSnapshot();

        $this->assertConflict(
            fn () => Entity::query()->findOrFail('s1')->createContents(['slug' => 'renamed']),
            '/renamed/page', 'x', 'en',
        );

        $this->assertSame($before, $this->routesSnapshot());
    }

    public function test_moving_into_a_conflict_aborts_the_move(): void
    {
        $this->home();
        $this->entity('s1', 'home', 'Section')->createContents(['slug' => 'a']);
        $this->entity('s2', 'home', 'Section')->createContents(['slug' => 'b']);
        $this->entity('p1', 's1')->createContents(['slug' => 'page']);
        $this->entity('p2', 's2')->createContents(['slug' => 'page']);

        $page = Entity::query()->findOrFail('p1');
        $page->parent_entity_id = 's2';

        $this->assertConflict(fn () => $page->save(), '/b/page', 'p2', 'en');
        $this->assertSame('s1', Entity::query()->findOrFail('p1')->parent_entity_id);
    }

    public function test_create_for_adds_an_alias_and_find_by_path_resolves_it(): void
    {
        $this->home();
        $this->entity('p1', 'home')->createContents(['slug' => 'page']);

        $alias = EntityRoute::createFor('p1', 'en', 'promo/', RouteKind::Alias);

        $this->assertSame('/promo', $alias->path);
        $this->assertSame('Page', $alias->entity_model);
        $this->assertSame('page', $alias->entity_view);
        $this->assertSame('p1', EntityRoute::findByPath('/promo')?->entity_id);
        $this->assertSame('/page', EntityRoute::query()->where('entity_id', 'p1')->where('kind', 'primary')->value('path'));
        $this->assertNull(EntityRoute::findByPath('/nothing'));
    }

    public function test_create_for_a_primary_demotes_the_current_primary(): void
    {
        $this->home();
        $this->entity('p1', 'home')->createContents(['slug' => 'page']);

        EntityRoute::createFor('p1', 'en', '/landing');

        $this->assertDatabaseHas('entities_routes', ['path' => '/landing', 'kind' => 'primary']);
        $this->assertDatabaseHas('entities_routes', ['path' => '/page', 'kind' => 'permanent-redirect']);
    }

    public function test_create_for_with_a_conflicting_path_throws(): void
    {
        $this->home();
        $this->entity('p1', 'home')->createContents(['slug' => 'page']);
        $this->entity('p2', 'home');

        $this->assertConflict(fn () => EntityRoute::createFor('p2', 'en', '/page', RouteKind::Alias), '/page', 'p1', 'en');
    }
}
