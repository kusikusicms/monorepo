<?php

namespace KusikusiCMS\Api\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use KusikusiCMS\Api\Api;
use KusikusiCMS\Api\Http\Resources\EntityResource;
use KusikusiCMS\Api\Services\EntityQuery;
use KusikusiCMS\Api\Support\Capabilities;
use KusikusiCMS\Api\Tests\Fixtures\User;
use KusikusiCMS\Api\Tests\TestCase;
use KusikusiCMS\Models\Builders\EntityBuilder;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\EntityContent;
use Laravel\Sanctum\Sanctum;

class ExtensionHooksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']));

        Entity::query()->create(['id' => 'root', 'model' => 'home']);

        foreach (['a', 'b', 'c'] as $id) {
            Entity::query()->create(['id' => $id, 'model' => 'page', 'parent_entity_id' => 'root'])
                ->createContents(['title' => strtoupper($id)], 'en');
        }
    }

    public function test_capabilities_are_public_and_list_the_api(): void
    {
        $this->getJson('/api/v1/capabilities')
            ->assertOk()
            ->assertJsonPath('data.api.version', 'v1')
            ->assertJsonPath('data.api.auth.modes', ['token', 'session'])
            ->assertJsonPath('data.api.auth.csrf_cookie_url', '/sanctum/csrf-cookie')
            ->assertJsonPath('data.packages.0.name', 'api')
            ->assertJsonPath('data.packages.0.features', ['entities', 'contents', 'relations', 'tree', 'auth.tokens', 'auth.session']);
    }

    public function test_packages_can_register_capabilities(): void
    {
        Api::capability('example', '13.1.0', ['things']);
        Api::capability('example', '13.2.0', ['things', 'more']);

        $this->assertCount(2, $this->app->make(Capabilities::class)->all());

        $this->getJson('/api/v1/capabilities')
            ->assertJsonPath('data.packages.1', ['name' => 'example', 'version' => '13.2.0', 'features' => ['things', 'more']]);
    }

    public function test_extension_fields_are_added_in_registration_order(): void
    {
        EntityResource::extend(fn (Entity $entity) => ['first' => $entity->id, 'shared' => 'first']);
        EntityResource::extend(fn (Entity $entity, Request $request) => ['shared' => 'second', 'lang' => $request->query('lang')]);

        $this->getJson('/api/v1/entities/a?lang=en')
            ->assertJsonPath('data.first', 'a')
            ->assertJsonPath('data.shared', 'second')
            ->assertJsonPath('data.lang', 'en');

        $this->getJson('/api/v1/entities?child_of=root')->assertJsonPath('data.*.first', ['a', 'b', 'c']);
    }

    public function test_prepare_runs_once_per_response_with_every_entity(): void
    {
        $calls = [];

        EntityResource::extend(
            function (Entity $entity) {
                return ['title_from_batch' => $entity->getAttribute('batch_title')];
            },
            function (Collection $entities) use (&$calls) {
                $calls[] = $entities->pluck('id')->sort()->values()->all();
                $titles = EntityContent::query()->whereIn('entity_id', $entities->pluck('id'))->where('field', 'title')->pluck('text', 'entity_id');
                $entities->each(fn (Entity $entity) => $entity->setAttribute('batch_title', $titles[$entity->id] ?? null));
            },
        );

        $this->getJson('/api/v1/entities?child_of=root')->assertJsonPath('data.*.title_from_batch', ['A', 'B', 'C']);
        $this->getJson('/api/v1/entities/b')->assertJsonPath('data.title_from_batch', 'B');
        $this->getJson('/api/v1/entities/root/tree')->assertJsonPath('data.children.*.title_from_batch', ['A', 'B', 'C']);

        $this->assertSame([['a', 'b', 'c'], ['b'], ['a', 'b', 'c', 'root']], $calls);
    }

    public function test_prepare_keeps_the_query_count_constant(): void
    {
        EntityResource::extend(
            fn (Entity $entity) => ['batch' => $entity->getAttribute('batch')],
            function (Collection $entities) {
                $counts = EntityContent::query()->whereIn('entity_id', $entities->pluck('id'))
                    ->selectRaw('entity_id, count(*) as total')->groupBy('entity_id')->pluck('total', 'entity_id');
                $entities->each(fn (Entity $entity) => $entity->setAttribute('batch', (int) ($counts[$entity->id] ?? 0)));
            },
        );

        $small = $this->countQueries('/api/v1/entities?per_page=1');

        foreach (range(1, 10) as $i) {
            Entity::query()->create(['id' => "x$i", 'model' => 'page', 'parent_entity_id' => 'root']);
        }

        $this->assertSame($small, $this->countQueries('/api/v1/entities?per_page=50'));
    }

    public function test_query_modifiers_apply_to_lists_and_single_entities(): void
    {
        $contexts = [];

        EntityQuery::extend(function (EntityBuilder $query, Request $request, string $context) use (&$contexts) {
            $contexts[] = $context;
            $query->where('entities.id', '!=', 'b');
        });

        $this->getJson('/api/v1/entities?child_of=root')->assertJsonPath('data.*.id', ['a', 'c']);
        $this->getJson('/api/v1/entities/b')->assertNotFound();
        $this->getJson('/api/v1/entities/root/children')->assertJsonPath('data.*.id', ['a', 'c']);

        $this->assertSame(['index', 'show', 'index'], $contexts);
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
}
