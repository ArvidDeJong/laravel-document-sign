<?php

namespace Darvis\Signer;

use Darvis\Signer\Services\AuditLogger;
use Darvis\Signer\Services\SignatureStamper;
use Darvis\Signer\Services\SignerManager;
use Darvis\Signer\Support\SignerConfig;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class SignerServiceProvider extends ServiceProvider
{
    /**
     * The named rate limiter on the portal login. A host app may register its
     * own limiter under this name in a provider that boots later.
     */
    public const LOGIN_RATE_LIMITER = 'signer-portal-login';

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

        // Five attempts a minute for an email address from one IP address, as Laravel's starter kits do.
        RateLimiter::for(self::LOGIN_RATE_LIMITER, function (Request $request): Limit {
            $email = $request->input('email');

            return Limit::perMinute(5)->by(
                Str::transliterate(Str::lower(is_string($email) ? $email : '')).'|'.$request->ip()
            );
        });

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
