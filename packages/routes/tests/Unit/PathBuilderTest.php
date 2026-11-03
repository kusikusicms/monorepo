<?php

namespace KusikusiCMS\Routes\Tests\Unit;

use InvalidArgumentException;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\EntityContent;
use KusikusiCMS\Routes\Services\PathBuilder;
use KusikusiCMS\Routes\Tests\TestCase;

class PathBuilderTest extends TestCase
{
    private PathBuilder $paths;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paths = app(PathBuilder::class);
    }

    private function entity(string $id, ?string $parent = null, string $model = 'Page'): Entity
    {
        return Entity::create(['id' => $id, 'parent_entity_id' => $parent, 'model' => $model]);
    }

    private function slug(string $entityId, string $slug, string $lang = 'en'): void
    {
        // Inserted through the query builder so no route syncing runs
        EntityContent::query()->insert(['entity_id' => $entityId, 'lang' => $lang, 'field' => 'slug', 'text' => $slug]);
    }

    public function test_root_with_empty_slug_gets_the_root_path(): void
    {
        $home = $this->entity('home', model: 'Home');
        $this->slug('home', '');

        $this->assertSame('/', $this->paths->build($home, 'en'));
    }

    public function test_root_with_a_slug_gets_a_prefix(): void
    {
        $home = $this->entity('home', model: 'Home');
        $this->slug('home', 'es', 'es');

        $this->assertSame('/es', $this->paths->build($home, 'es'));
    }

    public function test_three_level_chain(): void
    {
        $this->entity('home', model: 'Home');
        $this->entity('s1', 'home');
        $page = $this->entity('p1', 's1');
        $this->slug('s1', 'section1', 'es-419');
        $this->slug('p1', 'page1', 'es-419');

        $this->assertSame('/section1/page1', $this->paths->build($page, 'es-419'));
    }

    public function test_missing_ancestor_slug_gives_null(): void
    {
        $this->entity('home', model: 'Home');
        $this->entity('s1', 'home');
        $page = $this->entity('p1', 's1');
        $this->slug('p1', 'page1');

        $this->assertNull($this->paths->build($page, 'en'));
    }

    public function test_entity_without_slug_gives_null(): void
    {
        $this->entity('home', model: 'Home');
        $page = $this->entity('p1', 'home');

        $this->assertNull($this->paths->build($page, 'en'));
    }

    public function test_slug_override_is_used(): void
    {
        $this->entity('home', model: 'Home');
        $page = $this->entity('p1', 'home');
        $this->slug('p1', 'old');

        $this->assertSame('/new', $this->paths->build($page, 'en', 'new'));
    }

    public function test_entities_outside_the_root_tree_get_no_path(): void
    {
        $this->entity('home', model: 'Home');
        $this->entity('other', model: 'Home');
        $page = $this->entity('p1', 'other');
        $this->slug('p1', 'page');

        $this->assertNull($this->paths->build($page, 'en'));
        $this->assertFalse($this->paths->inScope($page));
    }

    public function test_without_root_every_tree_is_in_scope(): void
    {
        config(['kusikusicms.routes.domains' => ['localhost' => ['root_entity_id' => null, 'lang' => 'en']]]);
        $this->entity('other', model: 'Home');
        $page = $this->entity('p1', 'other');
        $this->slug('p1', 'page');

        $this->assertSame('/page', $this->paths->build($page, 'en'));
    }

    public function test_slugs_are_trimmed_and_cannot_contain_slashes(): void
    {
        $this->assertSame('page', $this->paths->sanitize(' /page/ '));

        $this->expectException(InvalidArgumentException::class);
        $this->paths->sanitize('a/b');
    }
}
