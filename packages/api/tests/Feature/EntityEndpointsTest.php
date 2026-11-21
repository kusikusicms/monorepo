<?php

namespace KusikusiCMS\Api\Tests\Feature;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use KusikusiCMS\Api\Tests\Fixtures\User;
use KusikusiCMS\Api\Tests\TestCase;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\EntityRelation;
use Laravel\Sanctum\Sanctum;

/**
 * api alone with unhandled writes allowed, so the endpoints themselves are exercised.
 */
class EntityEndpointsTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('kusikusicms.api.authorization.unhandled.write', 'allow');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']));
    }

    public function test_create_an_entity_with_contents(): void
    {
        $this->postJson('/api/v1/entities', [
            'id' => 'home',
            'model' => 'home',
        ])->assertCreated();

        $response = $this->postJson('/api/v1/entities?lang=en', [
            'id' => 'about',
            'model' => 'page',
            'parent_entity_id' => 'home',
            'props' => ['color' => 'red'],
            'langs' => ['en', 'es'],
            'contents' => ['en' => ['title' => 'About'], 'es' => ['title' => 'Acerca']],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.id', 'about')
            ->assertJsonPath('data.model', 'page')
            ->assertJsonPath('data.view', 'page')
            ->assertJsonPath('data.parent_entity_id', 'home')
            ->assertJsonPath('data.props.color', 'red')
            ->assertJsonPath('data.langs', ['en', 'es'])
            ->assertJsonPath('data.status', 'live')
            ->assertJsonPath('data.contents.title', 'About');

        $this->assertSame(2, Entity::query()->findOrFail('about')->rawContents()->count());
        $this->assertTrue(EntityRelation::query()->where([
            'caller_entity_id' => 'about', 'called_entity_id' => 'home', 'kind' => 'ancestor',
        ])->exists());
    }

    public function test_create_validates_input(): void
    {
        Entity::query()->create(['id' => 'home', 'model' => 'home']);

        $this->postJson('/api/v1/entities', [
            'id' => 'home',
            'parent_entity_id' => 'missing',
            'contents' => ['not a lang' => ['bad field!' => 'x']],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['id', 'model', 'parent_entity_id']]);
    }

    public function test_a_failing_content_write_rolls_back_the_entity(): void
    {
        Entity::saved(function (Entity $entity) {
            if ($entity->id === 'boom') {
                throw new \RuntimeException('listener failed');
            }
        });

        $this->postJson('/api/v1/entities', ['id' => 'boom', 'model' => 'page', 'contents' => ['en' => ['title' => 'x']]])
            ->assertStatus(500);

        $this->assertNull(Entity::query()->find('boom'));
    }

    public function test_show_with_includes(): void
    {
        $this->seedTree();
        EntityRelation::query()->create(['caller_entity_id' => 'a', 'called_entity_id' => 'b', 'kind' => 'related', 'position' => 2, 'tags' => ['x']]);

        $this->getJson('/api/v1/entities/a?include=contents,relations&lang=es')
            ->assertOk()
            ->assertJsonPath('data.contents.title', 'A es')
            ->assertJsonPath('data.contents_by_lang.en.title', 'A en')
            ->assertJsonPath('data.contents_by_lang.es.title', 'A es')
            ->assertJsonCount(1, 'data.relations')
            ->assertJsonPath('data.relations.0', [
                'caller_entity_id' => 'a', 'called_entity_id' => 'b', 'kind' => 'related', 'position' => 2, 'depth' => 0, 'tags' => ['x'],
            ]);

        $this->getJson('/api/v1/entities/missing')->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_index_is_paginated_filtered_and_sorted(): void
    {
        $this->seedTree();

        $this->getJson('/api/v1/entities?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonStructure(['data', 'links' => ['first', 'last', 'prev', 'next'], 'meta']);

        $this->getJson('/api/v1/entities?per_page=1000')->assertJsonPath('meta.per_page', 100);

        $this->getJson('/api/v1/entities?model=page&child_of=root&sort=-content:title&lang=en')
            ->assertOk()
            ->assertJsonPath('data.*.id', ['b', 'a'])
            ->assertJsonPath('data.0.contents.title', 'B en');

        $this->getJson('/api/v1/entities?descendant_of=root&depth=1&sort=id')->assertJsonPath('data.*.id', ['a', 'b']);
        $this->getJson('/api/v1/entities?sort=bogus')->assertUnprocessable();
    }

    public function test_tree_lists_and_nested_tree(): void
    {
        $this->seedTree();

        $this->getJson('/api/v1/entities/root/children?sort=id')->assertJsonPath('data.*.id', ['a', 'b']);
        $this->getJson('/api/v1/entities/root/descendants?sort=id')->assertJsonPath('data.*.id', ['a', 'a1', 'b']);
        $this->getJson('/api/v1/entities/a1/ancestors')->assertJsonPath('data.*.id', ['a', 'root']);
        $this->getJson('/api/v1/entities/a/siblings')->assertJsonPath('data.*.id', ['b']);

        $this->getJson('/api/v1/entities/root/tree?with_contents=1')
            ->assertOk()
            ->assertJsonPath('data.id', 'root')
            ->assertJsonPath('data.children.0.id', 'a')
            ->assertJsonPath('data.children.0.contents.title', 'A en')
            ->assertJsonPath('data.children.0.children.0.id', 'a1')
            ->assertJsonPath('data.children.1.id', 'b');

        $this->getJson('/api/v1/entities/root/tree?depth=1')->assertJsonCount(0, 'data.children.0.children');
    }

    public function test_update_delete_and_restore(): void
    {
        $this->seedTree();

        $this->patchJson('/api/v1/entities/a1', ['parent_entity_id' => 'b', 'published' => false, 'contents' => ['en' => ['title' => 'Moved']]])
            ->assertOk()
            ->assertJsonPath('data.parent_entity_id', 'b')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.model', 'page');

        $this->assertSame('Moved', Entity::query()->findOrFail('a1')->rawContents()->where('lang', 'en')->value('text'));
        $this->getJson('/api/v1/entities/b/children')->assertJsonPath('data.*.id', ['a1']);

        $this->patchJson('/api/v1/entities/root', ['parent_entity_id' => 'a1'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['parent_entity_id']]);

        $this->deleteJson('/api/v1/entities/b')->assertNoContent();
        $this->getJson('/api/v1/entities/b')->assertNotFound();
        $this->getJson('/api/v1/entities?trashed=only')->assertJsonPath('data.*.id', ['b']);

        $this->postJson('/api/v1/entities/b/restore')->assertOk()->assertJsonPath('data.deleted_at', null);
        $this->postJson('/api/v1/entities/b/restore')->assertNotFound();
    }

    public function test_contents_endpoints(): void
    {
        $this->seedTree();

        $this->getJson('/api/v1/entities/a/contents')
            ->assertOk()
            ->assertJsonPath('data.en.title', 'A en')
            ->assertJsonPath('data.es.title', 'A es');

        $this->putJson('/api/v1/entities/a/contents/en', ['title' => 'New', 'body' => null])
            ->assertOk()
            ->assertJsonPath('data.title', 'New')
            ->assertJsonPath('data.body', '');

        $this->putJson('/api/v1/entities/a/contents/en', ['bad field!' => 'x'])->assertUnprocessable();
        $this->putJson('/api/v1/entities/a/contents/en', [])->assertUnprocessable();

        $this->deleteJson('/api/v1/entities/a/contents/en/body')->assertNoContent();
        $this->deleteJson('/api/v1/entities/a/contents/en/body')->assertNotFound();
        $this->getJson('/api/v1/entities/a/contents')->assertJsonMissingPath('data.en.body');
    }

    public function test_relations_endpoints(): void
    {
        $this->seedTree();

        $this->postJson('/api/v1/entities/a/relations', ['called_entity_id' => 'b', 'kind' => 'featured', 'position' => 3, 'tags' => ['home']])
            ->assertCreated()
            ->assertJsonPath('data.called_entity_id', 'b')
            ->assertJsonPath('data.position', 3);

        $this->postJson('/api/v1/entities/a/relations', ['called_entity_id' => 'b', 'kind' => 'featured'])
            ->assertStatus(409)->assertJsonPath('code', 'conflict');
        $this->postJson('/api/v1/entities/a/relations', ['called_entity_id' => 'b', 'kind' => 'ancestor'])
            ->assertUnprocessable()->assertJsonStructure(['errors' => ['kind']]);

        $this->getJson('/api/v1/entities/a/relations')->assertJsonCount(1, 'data')->assertJsonPath('data.0.kind', 'featured');
        $this->getJson('/api/v1/entities/a/relations?kind=other')->assertJsonCount(0, 'data');

        $this->patchJson('/api/v1/entities/a/relations/b/featured', ['position' => 7, 'tags' => null])
            ->assertOk()->assertJsonPath('data.position', 7)->assertJsonPath('data.tags', []);
        $this->patchJson('/api/v1/entities/a/relations/root/ancestor', ['position' => 1])->assertUnprocessable();

        $this->getJson('/api/v1/entities?related_by=a')->assertJsonPath('data.*.id', ['b']);
        $this->getJson('/api/v1/entities?relating=b&kind=featured')->assertJsonPath('data.*.id', ['a']);

        $this->deleteJson('/api/v1/entities/a/relations/b/featured')->assertNoContent();
        $this->deleteJson('/api/v1/entities/a/relations/b/featured')->assertNotFound();
    }

    public function test_index_runs_a_constant_number_of_queries(): void
    {
        $this->seedTree();
        $small = $this->countQueries('/api/v1/entities?per_page=1&with_contents=1&include=contents,relations');

        foreach (range(1, 10) as $i) {
            Entity::query()->create(['id' => "extra$i", 'model' => 'page', 'parent_entity_id' => 'root'])
                ->createContents(['title' => "Extra $i"], 'en');
        }

        $large = $this->countQueries('/api/v1/entities?per_page=50&with_contents=1&include=contents,relations');

        $this->assertSame($small, $large);
    }

    protected function countQueries(string $uri): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($uri)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * root > a > a1, root > b. Pages have en/es titles.
     */
    protected function seedTree(): void
    {
        Entity::query()->create(['id' => 'root', 'model' => 'home']);

        foreach (['a' => 'root', 'b' => 'root', 'a1' => 'a'] as $id => $parent) {
            $entity = Entity::query()->create(['id' => $id, 'model' => 'page', 'parent_entity_id' => $parent]);
            $entity->createContents(['title' => strtoupper($id).' en'], 'en');
            $entity->createContents(['title' => strtoupper($id).' es'], 'es');
        }
    }
}
