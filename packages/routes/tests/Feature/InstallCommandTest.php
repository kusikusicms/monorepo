<?php

namespace KusikusiCMS\Routes\Tests\Feature;

use App\Http\Controllers\WebsiteController;
use Illuminate\Support\Facades\File;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Routes\Console\InstallCommand;
use KusikusiCMS\Routes\Tests\TestCase;

class InstallCommandTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('app.url', 'https://my-site.test');
        $app['config']->set('kusikusicms.models.default_language', 'es');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        File::delete([$this->configFile(), $this->controllerFile(), $this->viewFile()]);
        File::deleteDirectory(config_path('kusikusicms'), true) && @rmdir(config_path('kusikusicms'));
        @rmdir(resource_path('views/html'));
    }

    private function configFile(): string
    {
        return config_path('kusikusicms/routes.php');
    }

    private function controllerFile(): string
    {
        return app_path('Http/Controllers/WebsiteController.php');
    }

    private function viewFile(): string
    {
        return resource_path('views/html/home.blade.php');
    }

    /**
     * @return array<string, mixed>
     */
    private function publishedConfig(): array
    {
        return require $this->configFile();
    }

    public function test_it_uses_the_answers_to_the_prompts(): void
    {
        $this->artisan('kusikusicms:routes:install')
            ->expectsQuestion('What is the primary domain of the site?', 'example.com')
            ->expectsQuestion('What is the primary language of the site?', 'en-US')
            ->expectsQuestion('What is the id of the root entity? (empty for all entities)', 'root')
            ->assertSuccessful();

        $this->assertSame(
            ['example.com' => ['root_entity_id' => 'root', 'lang' => 'en-US']],
            $this->publishedConfig()['domains'],
        );
        $this->assertFileExists($this->controllerFile());
        $this->assertFileExists($this->viewFile());
    }

    public function test_it_defaults_to_the_app_url_host_and_the_models_default_language(): void
    {
        $this->artisan('k:routes:install', ['--no-interaction' => true])->assertSuccessful();

        $config = $this->publishedConfig();
        $this->assertSame(['my-site.test' => ['root_entity_id' => 'home', 'lang' => 'es']], $config['domains']);
        $this->assertSame(['*'], $config['models']);
        $this->assertSame('slug', $config['slug_field']);
    }

    public function test_options_skip_the_prompts_and_an_empty_root_means_all_entities(): void
    {
        $this->artisan('kusikusicms:routes:install', ['--domain' => 'opt.test', '--lang' => 'fr', '--root' => ''])
            ->assertSuccessful();

        $this->assertSame(['opt.test' => ['root_entity_id' => null, 'lang' => 'fr']], $this->publishedConfig()['domains']);
    }

    public function test_existing_files_are_not_overwritten_without_force(): void
    {
        File::ensureDirectoryExists(dirname($this->controllerFile()));
        File::put($this->controllerFile(), 'custom');

        $this->artisan('kusikusicms:routes:install', ['--no-interaction' => true])->assertSuccessful();
        $this->assertSame('custom', File::get($this->controllerFile()));

        $this->artisan('kusikusicms:routes:install', ['--no-interaction' => true, '--force' => true])->assertSuccessful();
        $this->assertSame(File::get(InstallCommand::stubPath('WebsiteController.stub')), File::get($this->controllerFile()));
    }

    public function test_the_stubs_can_be_published_with_vendor_publish(): void
    {
        $this->artisan('vendor:publish', ['--tag' => 'kusikusicms-routes-stubs'])->assertSuccessful();

        $this->assertFileExists($this->controllerFile());
        $this->assertFileExists($this->viewFile());
    }

    public function test_the_published_controller_and_view_render_the_home_page(): void
    {
        $this->artisan('kusikusicms:routes:install', ['--no-interaction' => true])->assertSuccessful();
        if (! class_exists(WebsiteController::class, false)) {
            require $this->controllerFile();
        }
        config(['kusikusicms.routes.controller' => WebsiteController::class]);
        Entity::create(['id' => 'home', 'model' => 'Home'])->createContents(['title' => 'Welcome home', 'slug' => ''], 'es');
        Entity::create(['id' => 's1', 'parent_entity_id' => 'home', 'model' => 'Section'])
            ->createContents(['title' => 'Section one', 'slug' => 'section-one'], 'es');

        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="es">', false)
            ->assertSee('<h1>Welcome home</h1>', false)
            ->assertSee('/section-one">Section one</a>', false);
    }
}
