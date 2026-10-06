<?php

declare(strict_types=1);

namespace Obelaw\SystemOne\Clef;

use Illuminate\Support\ServiceProvider;
use Obelaw\SystemOne\Clef\Drivers\CloudflareDriver;
use Obelaw\SystemOne\SystemOneManager;

class ClefServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/clef.php',
            'clef'
        );

        $this->app->singleton(CloudflareDriver::class, function ($app) {
            $config = (array) $app['config']->get('clef', []);

            return new CloudflareDriver($config);
        });

        $this->app->alias(CloudflareDriver::class, 'systemone.driver.cloudflare');
        $this->app->alias(CloudflareDriver::class, 'systemone.driver.clef');

        // Automatically wire Cloudflare driver into SystemOne manager when resolved
        $this->app->resolving('systemone', function (SystemOneManager $manager, $app) {
            $manager->extend('cloudflare', function () use ($app) {
                return $app->make(CloudflareDriver::class);
            });

            $manager->extend('clef', function () use ($app) {
                return $app->make(CloudflareDriver::class);
            });
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/clef.php' => config_path('clef.php'),
            ], 'clef-config');

            $this->publishes([
                __DIR__ . '/../config/clef.php' => config_path('clef.php'),
            ], 'systemone-clef-config');
        }
    }
}
