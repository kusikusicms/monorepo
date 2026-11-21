<?php

namespace KusikusiCMS\Routes\Tests\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Routes\Abilities;
use KusikusiCMS\Routes\EntityRoute;
use KusikusiCMS\Routes\Enums\RouteKind;
use Laravel\Sanctum\Sanctum;

class RoutesApiContributionTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSite();
        Sanctum::actingAs($this->makeUser());
    }

    public function test_entities_have_a_route_field(): void
    {
        $this->getJson('/api/v1/entities/about')->assertOk()->assertJsonPath('data.route', '/about');
        $this->getJson('/api/v1/entities?sort=id')->assertJsonPath('data.*.route', ['/about', '/blog', '/']);
        $this->getJson('/api/v1/entities/about?lang=es')->assertJsonPath('data.route', null);
    }

    public function test_the_route_field_runs_a_constant_number_of_queries(): void
    {
        $small = $this->countQueries('/api/v1/entities?per_page=1');

        foreach (range(1, 10) as $i) {
            Entity::query()->create(['id' => "p$i", 'model' => 'page', 'parent_entity_id' => 'blog'])
                ->createContents(['slug' => "post-$i"], 'en');
        }

        $this->assertSame($small, $this->countQueries('/api/v1/entities?per_page=50'));
    }

    public function test_route_writes_are_denied_without_a_handler(): void
    {
        $this->postJson('/api/v1/entities/about/routes', ['path' => '/about-us'])->assertForbidden();

        $id = EntityRoute::findByPath('/about')?->route_id;
        $this->deleteJson("/api/v1/routes/{$id}")->assertForbidden();
    }

    public function test_aliases_and_redirects_can_be_created_and_deleted(): void
    {
        $this->allowRouteAbilities();

        $alias = $this->postJson('/api/v1/entities/about/routes', ['path' => 'about-us/'])
            ->assertCreated()
            ->assertJsonPath('data.path', '/about-us')
            ->assertJsonPath('data.kind', 'alias')
            ->assertJsonPath('data.lang', 'en');

        $this->postJson('/api/v1/entities/about/routes', ['path' => '/old-about', 'kind' => 'permanent-redirect'])
            ->assertCreated()->assertJsonPath('data.kind', 'permanent-redirect');

        $this->postJson('/api/v1/entities/about/routes', ['path' => '/x', 'kind' => 'primary'])->assertUnprocessable();

        $this->getJson('/api/v1/entities/about/routes')->assertOk()->assertJsonCount(3, 'data');

        $this->deleteJson('/api/v1/routes/'.$alias->json('data.route_id'))->assertNoContent();
        $this->assertNull(EntityRoute::findByPath('/about-us'));
    }

    public function test_a_path_conflict_returns_409(): void
    {
        $this->allowRouteAbilities();

        $this->postJson('/api/v1/entities/about/routes', ['path' => '/blog'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'conflict');
    }

    public function test_primary_routes_cannot_be_deleted(): void
    {
        $this->allowRouteAbilities();
        $id = EntityRoute::findByPath('/about')?->route_id;

        $this->deleteJson("/api/v1/routes/{$id}")->assertStatus(409)->assertJsonPath('code', 'conflict');
        $this->assertNotNull(EntityRoute::findByPath('/about'));
    }

    public function test_paths_are_resolved(): void
    {
        EntityRoute::createFor('about', 'en', '/old-about', RouteKind::PermanentRedirect);

        $this->getJson('/api/v1/routes/resolve?path=/about.rss')
            ->assertOk()
            ->assertJsonPath('data.entity_id', 'about')
            ->assertJsonPath('data.kind', 'primary')
            ->assertJsonPath('data.format', 'rss')
            ->assertJsonPath('data.redirect_to', null);

        $this->getJson('/api/v1/routes/resolve?path=/old-about')
            ->assertJsonPath('data.status', 301)
            ->assertJsonPath('data.redirect_to', '/about');

        $this->getJson('/api/v1/routes/resolve?path=/nope')->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->getJson('/api/v1/routes/resolve')->assertUnprocessable();
    }

    public function test_the_capability_is_registered(): void
    {
        $packages = collect($this->getJson('/api/v1/capabilities')->assertOk()->json('data.packages'))->keyBy('name');

        $this->assertSame(['route-field', 'entity-routes', 'resolve'], $packages['routes']['features'] ?? null);
        $this->assertTrue($packages->has('api'));
    }

    protected function allowRouteAbilities(): void
    {
        Gate::define(Abilities::CREATE_ROUTE, fn () => true);
        Gate::define(Abilities::DELETE_ROUTE, fn () => true);
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
