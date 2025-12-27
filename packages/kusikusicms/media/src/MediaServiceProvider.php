<?php

namespace KusikusiCMS\Media;

use Illuminate\Support\ServiceProvider;

class MediaServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/media.php', 'kusikusicms.media');
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/media.php' => config_path('kusikusicms/media.php'),
        ], 'kusikusicms-config');

        if (file_exists(__DIR__.'/../routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }
    }
}
