<?php

namespace KusikusiCMS\Api;

use Composer\InstalledVersions;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use KusikusiCMS\Api\Support\ApiErrorRenderer;
use KusikusiCMS\Api\Support\Capabilities;
use KusikusiCMS\Models\Entity;

class ApiServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/api.php', 'kusikusicms.api');

        $this->app->singleton(Capabilities::class);
    }

    /**
     * Boot the deny-by-default authorization, the error renderer, the about entry and publishable configuration.
     */
    public function boot(): void
    {
        Gate::after(function ($user, string $ability, ?bool $result, array $arguments = []) {
            if ($result !== null || ! $this->isPackageAbility($ability, $arguments)) {
                return $result;
            }

            return config('kusikusicms.api.authorization.unhandled.'.$this->unhandledKind($ability), 'deny') === 'allow';
        });

        Api::routes(fn () => require __DIR__.'/../routes/api.php');

        $features = ['entities', 'contents', 'relations', 'tree', 'auth.tokens'];

        if (Api::spaEnabled()) {
            $features[] = 'auth.session';
        }

        Api::capability('api', $this->packageVersion(), $features);

        ApiErrorRenderer::register($this->app);

        AboutCommand::add('KusikusiCMS API package', fn () => [
            'Version' => $this->packageVersion(),
            'Prefix' => config('kusikusicms.api.prefix'),
            'Unhandled reads' => config('kusikusicms.api.authorization.unhandled.read'),
            'Unhandled writes' => config('kusikusicms.api.authorization.unhandled.write'),
            'SPA cookie auth' => Api::spaEnabled() ? 'enabled' : 'disabled',
            'SPA guard' => $this->spaGuardStatus(),
        ]);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/api.php' => config_path('kusikusicms/api.php'),
            ], ['kusikusicms-config', 'kusikusicms-api-config']);
        }
    }

    /**
     * Only KusikusiCMS abilities and Entity policy abilities are answered by the deny-by-default callback.
     */
    protected function isPackageAbility(string $ability, array $arguments): bool
    {
        if (str_starts_with($ability, 'kusikusicms.')) {
            return true;
        }

        $subject = $arguments[0] ?? null;

        return $subject instanceof Entity || $subject === Entity::class;
    }

    /**
     * Reads are the view-like abilities; everything else is a write.
     */
    protected function unhandledKind(string $ability): string
    {
        return in_array($ability, ['viewAny', 'view'], true) ? 'read' : 'write';
    }

    /**
     * The SPA session guard, with a warning when it is not one of Sanctum's guards.
     */
    protected function spaGuardStatus(): string
    {
        $guard = (string) config('kusikusicms.api.auth.spa.guard', 'web');

        if (Api::spaEnabled() && ! in_array($guard, (array) config('sanctum.guard', ['web']), true)) {
            return '<fg=yellow;options=bold>'.$guard.' (does not match sanctum.guard)</>';
        }

        return $guard;
    }

    /**
     * Resolve the installed package version from Composer, falling back to "dev".
     */
    protected function packageVersion(): string
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('kusikusicms/api')) {
            return InstalledVersions::getPrettyVersion('kusikusicms/api') ?? 'dev';
        }

        return 'dev';
    }
}
