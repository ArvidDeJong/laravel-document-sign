<?php

namespace Darvis\Signer;

use Darvis\Signer\Services\AuditLogger;
use Darvis\Signer\Services\SignatureStamper;
use Darvis\Signer\Services\SignerManager;
use Darvis\Signer\Support\SignerConfig;
use Illuminate\Support\ServiceProvider;

class SignerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/signer.php', 'signer');

        $this->app->singleton(SignerConfig::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(SignatureStamper::class);
        $this->app->singleton('signer', fn ($app) => $app->make(SignerManager::class));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'signer');
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');

        if ($this->app->make(SignerConfig::class)->portalEnabled()) {
            $this->loadRoutesFrom(__DIR__.'/../routes/portal.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/signer.php' => config_path('signer.php'),
            ], 'signer-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/signer'),
            ], 'signer-views');
        }
    }
}
