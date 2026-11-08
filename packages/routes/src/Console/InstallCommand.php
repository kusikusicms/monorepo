<?php

namespace KusikusiCMS\Routes\Console;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\File;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Routes\Services\RouteManager;
use RuntimeException;

use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kusikusicms:routes:install
        {--domain= : The primary domain of the site}
        {--lang= : The primary language of the site}
        {--root= : The id of the root entity (empty for all entities)}
        {--force : Overwrite existing files}';

    protected $aliases = ['k:routes:install'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish the KusikusiCMS routes config, website controller and sample view';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $domain = $this->value('domain', 'What is the primary domain of the site?', $this->defaultDomain());
        $lang = $this->value('lang', 'What is the primary language of the site?', (string) config('kusikusicms.models.default_language', 'en'));
        $root = $this->value('root', 'What is the id of the root entity? (empty for all entities)', 'home', false);

        if ($domain === '' || $lang === '') {
            $this->components->error('The domain and the language are required.');

            return self::FAILURE;
        }

        $this->write(config_path('kusikusicms/routes.php'), $this->config($domain, $lang, $root === '' ? null : $root));
        $this->write(app_path('Http/Controllers/WebsiteController.php'), (string) File::get(static::stubPath('WebsiteController.stub')));
        $this->write(resource_path('views/html/home.blade.php'), (string) File::get(static::stubPath('views/html/home.blade.stub')));

        $this->syncRoot($domain, $lang, $root === '' ? null : $root);

        $this->components->info('KusikusiCMS routes installed. Remove any "/" route of your app that would shadow the CMS home.');

        return self::SUCCESS;
    }

    /**
     * Create the "/" route when the root entity already exists (it needs no slug content).
     */
    protected function syncRoot(string $domain, string $lang, ?string $root): void
    {
        if ($root === null) {
            return;
        }

        try {
            // The published config is not loaded yet, so use the answers for this run
            config(['kusikusicms.routes.domains' => [$domain => ['root_entity_id' => $root, 'lang' => $lang]]]);
            if (Entity::query()->whereKey($root)->exists()) {
                app(RouteManager::class)->syncEntity($root, $lang);
                $this->components->info("Synced the routes of the root entity [{$root}].");
            }
        } catch (QueryException) {
            $this->components->warn('Could not sync the root entity route (run the migrations first).');
        }
    }

    /**
     * The path of a stub shipped with the package.
     */
    public static function stubPath(string $stub = ''): string
    {
        return dirname(__DIR__, 2).'/stubs'.($stub === '' ? '' : '/'.$stub);
    }

    /**
     * The package config file with the domain, language and root entity id filled in.
     *
     * @throws RuntimeException When the package config no longer has the expected entries
     */
    public function config(string $domain, string $lang, ?string $root): string
    {
        $replacements = [
            '/^(\s*)env\(\'KUSIKUSICMS_DOMAIN\'.*=> \[$/m' => '${1}'.var_export($domain, true).' => [',
            '/^(\s*)\'root_entity_id\' => .*,$/m' => '${1}\'root_entity_id\' => '.($root === null ? 'null' : var_export($root, true)).',',
            '/^(\s*)\'lang\' => env\(\'KUSIKUSICMS_LANG\'.*,$/m' => '${1}\'lang\' => '.var_export($lang, true).',',
        ];

        $config = (string) File::get(dirname(__DIR__, 2).'/config/routes.php');
        foreach ($replacements as $pattern => $replacement) {
            $config = (string) preg_replace($pattern, str_replace('\\', '\\\\', $replacement), $config, 1, $count);
            if ($count !== 1) {
                throw new RuntimeException("The routes config template does not match [{$pattern}].");
            }
        }

        return $config;
    }

    /**
     * An option value, or the answer to a prompt (the default when not interactive).
     */
    protected function value(string $option, string $label, string $default, bool $required = true): string
    {
        $value = $this->option($option);
        if (is_string($value)) {
            return trim($value);
        }
        if (! $this->input->isInteractive()) {
            return $default;
        }

        return trim(text(label: $label, default: $default, required: $required));
    }

    /**
     * The host of the app.url config, or "localhost".
     */
    protected function defaultDomain(): string
    {
        $host = parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    /**
     * Write a file unless it exists and --force is not given.
     */
    protected function write(string $path, string $contents): void
    {
        if (File::exists($path) && ! $this->option('force')) {
            $this->components->warn("Skipped (already exists, use --force to overwrite): {$path}");

            return;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
        $this->components->info("Created: {$path}");
    }
}
