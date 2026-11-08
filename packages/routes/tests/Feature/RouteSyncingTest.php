<?php

namespace KusikusiCMS\Routes\Tests\Feature;

use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\EntityContent;
use KusikusiCMS\Routes\EntityRoute;
use KusikusiCMS\Routes\Enums\RouteKind;
use KusikusiCMS\Routes\Tests\TestCase;

class RouteSyncingTest extends TestCase
{
    private function entity(string $id, ?string $parent = null, string $model = 'Page'): Entity
    {
        return Entity::create(['id' => $id, 'parent_entity_id' => $parent, 'model' => $model]);
    }

    /**
     * home (/) > s1 (/section1) > p1 (/section1/page1)
     */
    private function tree(): void
    {
        $this->entity('home', model: 'Home')->createContents(['title' => 'Home', 'slug' => '']);
        $this->entity('s1', 'home', 'Section')->createContents(['title' => 'Section', 'slug' => 'section1']);
        $this->entity('p1', 's1')->createContents(['title' => 'Page', 'slug' => 'page1']);
    }

    private function primaryPath(string $entityId, string $lang = 'en'): ?string
    {
        return EntityRoute::query()
            ->where('entity_id', $entityId)->where('lang', $lang)->where('kind', RouteKind::Primary->value)
            ->value('path');
    }

    public function test_creating_a_slug_creates_a_primary_route_with_denormalized_data(): void
    {
        $this->tree();

        $this->assertDatabaseHas('entities_routes', [
            'entity_id' => 'p1', 'path' => '/section1/page1', 'entity_model' => 'Page',
            'entity_view' => 'page', 'lang' => 'en', 'kind' => 'primary',
        ]);
        $this->assertSame('/', $this->primaryPath('home'));
        $this->assertSame('/section1', $this->primaryPath('s1'));
        $this->assertSame(3, EntityRoute::query()->count());
    }

    public function test_changing_a_slug_turns_the_old_primary_into_a_permanent_redirect(): void
    {
        $this->tree();

        Entity::query()->findOrFail('p1')->createContents(['slug' => 'renamed']);

        $this->assertSame('/section1/renamed', $this->primaryPath('p1'));
        $this->assertDatabaseHas('entities_routes', ['entity_id' => 'p1', 'path' => '/section1/page1', 'kind' => 'permanent-redirect']);
    }

    public function test_reverting_a_slug_promotes_the_redirect_back(): void
    {
        $this->tree();
        $page = Entity::query()->findOrFail('p1');

        $page->createContents(['slug' => 'renamed']);
        $page->createContents(['slug' => 'page1']);

        $this->assertSame('/section1/page1', $this->primaryPath('p1'));
        $this->assertDatabaseHas('entities_routes', ['path' => '/section1/renamed', 'kind' => 'permanent-redirect']);
        $this->assertSame(2, EntityRoute::query()->where('entity_id', 'p1')->count());
    }

    public function test_saving_the_same_slug_changes_nothing(): void
    {
        $this->tree();
        $before = EntityRoute::query()->orderBy('route_id')->get()->toArray();

        Entity::query()->findOrFail('p1')->createContents(['slug' => 'page1', 'title' => 'New title']);

        $this->assertSame($before, EntityRoute::query()->orderBy('route_id')->get()->toArray());
    }

    public function test_non_slug_fields_are_ignored(): void
    {
        $this->entity('home', model: 'Home')->createContents(['title' => 'Home', 'body' => 'Text']);
        $this->entity('p1', 'home')->createContents(['title' => 'Page', 'body' => 'Text']);

        $this->assertSame(['home'], EntityRoute::query()->pluck('entity_id')->all());
    }

    public function test_excepted_models_get_no_routes_but_their_slug_still_builds_child_paths(): void
    {
        config(['kusikusicms.routes.except_models' => ['Section']]);
        $this->tree();

        $this->assertNull($this->primaryPath('s1'));
        $this->assertSame('/section1/page1', $this->primaryPath('p1'));
    }

    public function test_only_listed_models_get_routes(): void
    {
        config(['kusikusicms.routes.models' => ['Home']]);
        $this->tree();

        $this->assertSame(['home'], EntityRoute::query()->pluck('entity_id')->all());
    }

    public function test_entities_outside_the_root_tree_are_ignored(): void
    {
        $this->entity('home', model: 'Home');
        $this->entity('drafts', model: 'Folder')->createContents(['slug' => '']);
        $this->entity('d1', 'drafts')->createContents(['slug' => 'draft']);

        $this->assertSame(['home'], EntityRoute::query()->pluck('entity_id')->all());
    }

    public function test_multi_language_contents_create_one_primary_per_language(): void
    {
        $this->entity('home', model: 'Home')->createContents(['slug' => '']);
        Entity::query()->findOrFail('home')->createContents(['slug' => 'es'], 'es');
        $this->entity('p1', 'home')->createContents(['slug' => 'page']);
        Entity::query()->findOrFail('p1')->createContents(['slug' => 'pagina'], 'es');

        $this->assertSame('/', $this->primaryPath('home', 'en'));
        $this->assertSame('/es', $this->primaryPath('home', 'es'));
        $this->assertSame('/page', $this->primaryPath('p1', 'en'));
        $this->assertSame('/es/pagina', $this->primaryPath('p1', 'es'));
    }

    public function test_changing_a_section_slug_cascades_to_descendants(): void
    {
        $this->tree();

        Entity::query()->findOrFail('s1')->createContents(['slug' => 'news']);

        $this->assertSame('/news', $this->primaryPath('s1'));
        $this->assertSame('/news/page1', $this->primaryPath('p1'));
        $this->assertDatabaseHas('entities_routes', ['entity_id' => 'p1', 'path' => '/section1/page1', 'kind' => 'permanent-redirect']);
    }

    public function test_moving_an_entity_repaths_it_and_its_descendants(): void
    {
        $this->tree();
        $this->entity('s2', 'home', 'Section')->createContents(['slug' => 'section2']);
        $this->entity('c1', 'p1')->createContents(['slug' => 'child']);

        $page = Entity::query()->findOrFail('p1');
        $page->parent_entity_id = 's2';
        $page->save();

        $this->assertSame('/section2/page1', $this->primaryPath('p1'));
        $this->assertSame('/section2/page1/child', $this->primaryPath('c1'));
        $this->assertDatabaseHas('entities_routes', ['entity_id' => 'c1', 'path' => '/section1/page1/child', 'kind' => 'permanent-redirect']);
    }

    public function test_moving_an_entity_out_of_the_root_tree_removes_its_primary_routes(): void
    {
        $this->tree();
        $this->entity('trash', model: 'Folder');

        $page = Entity::query()->findOrFail('p1');
        $page->parent_entity_id = 'trash';
        $page->save();

        $this->assertNull($this->primaryPath('p1'));
    }

    public function test_changing_model_or_view_updates_the_denormalized_columns(): void
    {
        $this->tree();

        $page = Entity::query()->findOrFail('p1');
        $page->model = 'BlogPost';
        $page->setAttribute('view', 'blog_post');
        $page->save();

        $this->assertDatabaseHas('entities_routes', ['entity_id' => 'p1', 'entity_model' => 'BlogPost', 'entity_view' => 'blog_post']);
    }

    public function test_deleting_a_slug_removes_the_primary_and_cascades(): void
    {
        $this->tree();

        EntityContent::query()->where('entity_id', 's1')->where('field', 'slug')->firstOrFail()->delete();

        $this->assertNull($this->primaryPath('s1'));
        $this->assertNull($this->primaryPath('p1'));
        $this->assertSame('/', $this->primaryPath('home'));
    }

    public function test_direct_content_creation_syncs_routes(): void
    {
        $this->entity('home', model: 'Home');
        $page = $this->entity('p1', 'home');

        $page->rawContents()->create(['lang' => 'en', 'field' => 'slug', 'text' => 'direct']);

        $this->assertSame('/direct', $this->primaryPath('p1'));
    }

    public function test_force_deleting_an_entity_removes_its_routes(): void
    {
        $this->tree();

        Entity::query()->findOrFail('p1')->forceDelete();

        $this->assertSame(0, EntityRoute::query()->where('entity_id', 'p1')->count());
    }

    public function test_soft_deleted_entities_keep_their_routes(): void
    {
        $this->tree();

        Entity::query()->findOrFail('p1')->delete();

        $this->assertSame('/section1/page1', $this->primaryPath('p1'));
    }

    public function test_the_root_gets_a_slash_route_without_slug_content(): void
    {
        $this->entity('home', model: 'Home');
        $this->entity('s1', 'home', 'Section')->createContents(['slug' => 'section1']);

        $this->assertSame('/', $this->primaryPath('home'));
        $this->assertSame('/section1', $this->primaryPath('s1'));
    }

    public function test_the_root_without_slug_only_gets_a_route_in_the_domain_language(): void
    {
        $this->entity('home', model: 'Home')->createContents(['title' => 'Inicio'], 'es');

        $this->assertSame('/', $this->primaryPath('home', 'en'));
        $this->assertNull($this->primaryPath('home', 'es'));
    }

    public function test_deleting_the_root_slug_keeps_the_slash_route(): void
    {
        $this->entity('home', model: 'Home')->createContents(['slug' => 'start']);
        $this->assertSame('/start', $this->primaryPath('home'));

        EntityContent::query()->where('entity_id', 'home')->where('field', 'slug')->get()->each->delete();

        $this->assertSame('/', $this->primaryPath('home'));
    }
}
