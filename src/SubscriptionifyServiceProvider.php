<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Revoltify\Subscriptionify\Console\Commands\ExpireOverdueSubscriptions;
use Revoltify\Subscriptionify\Http\Middleware\EnsureFeature;
use Revoltify\Subscriptionify\Http\Middleware\EnsurePlan;
use Revoltify\Subscriptionify\Http\Middleware\EnsureSubscribed;
use Revoltify\Subscriptionify\Services\FeatureGrantService;
use Revoltify\Subscriptionify\Services\FeatureInfoBuilder;
use Revoltify\Subscriptionify\Services\FeatureResolver;
use Revoltify\Subscriptionify\Services\FeatureService;
use Revoltify\Subscriptionify\Support\BladeDirectives;

final class SubscriptionifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/subscriptionify.php', 'subscriptionify');

        $this->app->singleton(FeatureResolver::class);
        $this->app->singleton(FeatureInfoBuilder::class);
        $this->app->singleton(FeatureGrantService::class);
        $this->app->singleton(FeatureService::class);
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->publishMigrations();
        $this->registerMiddleware();
        $this->registerCommands();

        BladeDirectives::register();
    }

    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__.'/../config/subscriptionify.php' => config_path('subscriptionify.php'),
        ], 'subscriptionify-config');
    }

    private function publishMigrations(): void
    {
        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'subscriptionify-migrations');
    }

    private function registerMiddleware(): void
    {
        $config = config()->array('subscriptionify.middleware', []);

        Route::aliasMiddleware(
            is_string($config['subscribed'] ?? null) ? $config['subscribed'] : 'subscribed',
            EnsureSubscribed::class,
        );

        Route::aliasMiddleware(
            is_string($config['plan'] ?? null) ? $config['plan'] : 'plan',
            EnsurePlan::class,
        );

        Route::aliasMiddleware(
            is_string($config['feature'] ?? null) ? $config['feature'] : 'feature',
            EnsureFeature::class,
        );
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpireOverdueSubscriptions::class,
            ]);
        }
    }
}
