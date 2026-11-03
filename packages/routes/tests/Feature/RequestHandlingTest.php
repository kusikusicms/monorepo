<?php

namespace KusikusiCMS\Routes\Tests\Feature;

use Illuminate\Routing\Router;
use Illuminate\Support\Carbon;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Routes\EntityRoute;
use KusikusiCMS\Routes\Enums\RouteKind;
use KusikusiCMS\Routes\Tests\Fixtures\TestWebsiteController;
use KusikusiCMS\Routes\Tests\TestCase;
use LogicException;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\Attributes\DefineRoute;

class RequestHandlingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('kusikusicms.routes.controller', TestWebsiteController::class);
    }

    protected function disableCatchAll($app): void
    {
        $app['config']->set('kusikusicms.routes.catch_all.enabled', false);
    }

    protected function appRoutes(Router $router): void
    {
        $router->get('/section1', fn () => 'app route');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function entity(string $id, ?string $parent, string $model, array $attributes = []): Entity
    {
        return Entity::create(['id' => $id, 'parent_entity_id' => $parent, 'model' => $model] + $attributes);
    }

    /**
     * home (/) > s1 (/section1) > p1 (/section1/page1), b1 (/section1/post1)
     */
    private function tree(): void
    {
        $this->entity('home', null, 'Home')->createContents(['slug' => '']);
        $this->entity('s1', 'home', 'Section')->createContents(['slug' => 'section1']);
        $this->entity('p1', 's1', 'Page')->createContents(['slug' => 'page1']);
        $this->entity('b1', 's1', 'BlogPost')->createContents(['slug' => 'post1']);
    }

    public function test_a_primary_route_calls_the_model_method_with_the_resolved_route(): void
    {
        $this->tree();

        $this->get('/section1/page1')->assertOk()->assertExactJson([
            'method' => 'page', 'entityId' => 'p1', 'entityModel' => 'Page', 'entityView' => 'page',
            'format' => 'html', 'lang' => 'en', 'path' => '/section1/page1', 'kind' => 'primary', 'view' => 'html.page',
        ]);
    }

    public function test_the_root_path_and_camel_cased_models_are_dispatched(): void
    {
        $this->tree();

        $this->get('/')->assertOk()->assertJson(['method' => 'home', 'entityId' => 'home', 'path' => '/']);
        $this->get('/index.html')->assertOk()->assertJson(['method' => 'home']);
        $this->get('/section1/post1')->assertOk()->assertJson(['method' => 'blogPost', 'view' => 'html.blog_post']);
    }

    public function test_the_format_comes_from_the_extension(): void
    {
        $this->tree();

        $this->get('/section1/page1.rss')->assertOk()->assertJson(['format' => 'rss', 'view' => 'rss.page']);
        $this->get('/section1/page1/index.amp')->assertOk()->assertJson(['format' => 'amp']);
        $this->get('/section1/page1/')->assertOk()->assertJson(['format' => 'html']);
    }

    public function test_an_alias_resolves_like_a_primary(): void
    {
        $this->tree();
        EntityRoute::createFor('p1', 'en', '/short', RouteKind::Alias);

        $this->get('/short')->assertOk()->assertJson(['method' => 'page', 'entityId' => 'p1', 'path' => '/short', 'kind' => 'alias']);
    }

    public function test_a_permanent_redirect_responds_301_to_the_primary_keeping_format_and_query(): void
    {
        $this->tree();
        Entity::query()->findOrFail('p1')->createContents(['slug' => 'renamed']);

        $this->get('/section1/page1')->assertStatus(301)->assertRedirect('/section1/renamed');
        $this->get('/section1/page1.rss?page=2&q=a')->assertStatus(301)->assertRedirect('/section1/renamed.rss?page=2&q=a');
        $this->get('/section1/page1.html')->assertStatus(301)->assertRedirect('/section1/renamed');
    }

    public function test_a_temporary_redirect_responds_302(): void
    {
        $this->tree();
        EntityRoute::createFor('p1', 'en', '/promo', RouteKind::TemporaryRedirect);

        $this->get('/promo')->assertStatus(302)->assertRedirect('/section1/page1');
    }

    public function test_a_redirect_without_primary_responds_404(): void
    {
        $this->tree();
        $this->entity('x1', 'home', 'Page');
        EntityRoute::createFor('x1', 'en', '/old', RouteKind::PermanentRedirect);

        $this->get('/old')->assertNotFound();
    }

    public function test_unknown_paths_and_formats_respond_404(): void
    {
        $this->tree();

        $this->get('/nothing-here')->assertNotFound();
        $this->get('/section1/page1.php')->assertNotFound();
    }

    public function test_entities_that_are_not_live_respond_404(): void
    {
        $this->tree();
        $this->entity('u1', 's1', 'Page', ['published' => false])->createContents(['slug' => 'unpublished']);
        $this->entity('f1', 's1', 'Page', ['publish_at' => Carbon::now()->addDay()])->createContents(['slug' => 'scheduled']);
        $this->entity('e1', 's1', 'Page', ['unpublish_at' => Carbon::now()->subDay()])->createContents(['slug' => 'expired']);
        $this->entity('t1', 's1', 'Page')->createContents(['slug' => 'trashed']);
        Entity::query()->findOrFail('t1')->delete();

        $this->get('/section1/unpublished')->assertNotFound();
        $this->get('/section1/scheduled')->assertNotFound();
        $this->get('/section1/expired')->assertNotFound();
        $this->get('/section1/trashed')->assertNotFound();
        $this->assertNotNull(EntityRoute::findByPath('/section1/trashed'));
    }

    public function test_models_without_a_public_method_use_the_fallback(): void
    {
        $this->tree();
        $this->entity('pr1', 's1', 'Product')->createContents(['slug' => 'product1']);

        $this->get('/section1/product1')->assertNotFound();
    }

    #[DefineRoute('appRoutes')]
    public function test_app_routes_win_over_the_catch_all(): void
    {
        $this->tree();

        $this->get('/section1')->assertOk()->assertSee('app route');
        $this->get('/section1/page1')->assertOk()->assertJson(['method' => 'page']);
    }

    #[DefineEnvironment('disableCatchAll')]
    public function test_the_catch_all_can_be_disabled(): void
    {
        $this->tree();

        $this->assertFalse(app('router')->has('kusikusicms.routes.catch-all'));
        $this->get('/section1/page1')->assertNotFound();
    }

    public function test_the_catch_all_is_a_named_fallback_route(): void
    {
        $route = app('router')->getRoutes()->getByName('kusikusicms.routes.catch-all');

        $this->assertNotNull($route);
        $this->assertTrue($route->isFallback);
        $this->assertContains('web', $route->middleware());
    }

    public function test_a_controller_not_extending_the_base_class_fails(): void
    {
        $this->tree();
        config(['kusikusicms.routes.controller' => \stdClass::class]);

        $this->withoutExceptionHandling();
        $this->expectException(LogicException::class);

        $this->get('/section1/page1');
    }
}
