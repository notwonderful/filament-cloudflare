<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare;

use Illuminate\Support\ServiceProvider;
use notwonderful\FilamentCloudflare\Auth\CloudflareAuth;
use notwonderful\FilamentCloudflare\Contracts\CloudflareAuthInterface;
use notwonderful\FilamentCloudflare\Contracts\CloudflareClientInterface;
use notwonderful\FilamentCloudflare\Contracts\CloudflareSettingsInterface;
use notwonderful\FilamentCloudflare\Http\CloudflareClient;
use notwonderful\FilamentCloudflare\Services\CloudflareSettingsService;

class CloudflareServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-cloudflare');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/cloudflare.php' => config_path('cloudflare.php'),
        ], 'cloudflare-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/vendor/filament-cloudflare'),
        ], 'cloudflare-migrations');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/cloudflare.php', 'cloudflare');

        $this->app->singleton(CloudflareSettingsInterface::class, CloudflareSettingsService::class);
        $this->app->singleton(CloudflareAuthInterface::class, CloudflareAuth::class);
        $this->app->singleton(CloudflareClientInterface::class, CloudflareClient::class);

        $this->app->scoped('filament-cloudflare', Cloudflare::class);
    }
}
