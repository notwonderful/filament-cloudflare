<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare;

use Filament\Contracts\Plugin;
use Filament\FilamentManager;
use Filament\Panel;
use notwonderful\FilamentCloudflare\Pages\CloudflareCache;
use notwonderful\FilamentCloudflare\Pages\CloudflareEdgeCaching;
use notwonderful\FilamentCloudflare\Pages\CloudflareSettings;
use notwonderful\FilamentCloudflare\Resources\CloudflareAccessResource;
use notwonderful\FilamentCloudflare\Resources\CloudflareAnalyticsResource;
use notwonderful\FilamentCloudflare\Resources\CloudflareCacheRulesResource;
use notwonderful\FilamentCloudflare\Resources\CloudflareFirewallResource;
use notwonderful\FilamentCloudflare\Resources\CloudflarePageRulesResource;
use notwonderful\FilamentCloudflare\Resources\CloudflareAnalyticsResource\Widgets;
use notwonderful\FilamentCloudflare\Resources\CloudflareUserAgentRulesResource;

class CloudflarePlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-cloudflare';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    /** @return static */
    public static function get(): FilamentManager | static
    {
        /** @var static */
        return filament(app(static::class)->getId());
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                CloudflareSettings::class,
                CloudflareCache::class,
                CloudflareEdgeCaching::class,
            ])
            ->resources([
                CloudflareFirewallResource::class,
                CloudflareUserAgentRulesResource::class,
                CloudflareCacheRulesResource::class,
                CloudflarePageRulesResource::class,
                CloudflareAnalyticsResource::class,
                CloudflareAccessResource::class,
            ])
            ->widgets([
                Widgets\AnalyticsOverview::class,
                Widgets\UniqueVisitorsChart::class,
                Widgets\RequestsChart::class,
                Widgets\CachedRequestsPercentChart::class,
                Widgets\BytesChart::class,
                Widgets\CachedBytesChart::class,
                Widgets\ThreatsChart::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
