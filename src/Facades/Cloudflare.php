<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Facades;

use Illuminate\Support\Facades\Facade;
use notwonderful\FilamentCloudflare\Contracts\CloudflareSettingsInterface;
use notwonderful\FilamentCloudflare\Services\Access\CloudflareAccessService;
use notwonderful\FilamentCloudflare\Services\Analytics\CloudflareAnalyticsService;
use notwonderful\FilamentCloudflare\Services\Cache\CloudflareCacheService;
use notwonderful\FilamentCloudflare\Services\CacheRules\CloudflareCacheRulesService;
use notwonderful\FilamentCloudflare\Services\Dns\CloudflareDnsService;
use notwonderful\FilamentCloudflare\Services\EdgeCaching\CloudflareEdgeCachingService;
use notwonderful\FilamentCloudflare\Services\Firewall\CloudflareFirewallService;
use notwonderful\FilamentCloudflare\Services\PageRules\CloudflarePageRulesService;
use notwonderful\FilamentCloudflare\Services\Zone\CloudflareZoneService;

/**
 * @method static CloudflareZoneService zone()
 * @method static CloudflareCacheService cache()
 * @method static CloudflareDnsService dns()
 * @method static CloudflareFirewallService firewall()
 * @method static CloudflareCacheRulesService cacheRules()
 * @method static CloudflarePageRulesService pageRules()
 * @method static CloudflareAnalyticsService analytics()
 * @method static CloudflareAccessService access()
 * @method static CloudflareEdgeCachingService edgeCaching()
 * @method static CloudflareSettingsInterface settings()
 * @method static bool verifyCredentials()
 * @method static string|null getZoneId()
 * @method static string|null getAccountId()
 *
 * @see \notwonderful\FilamentCloudflare\Cloudflare
 */
class Cloudflare extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'filament-cloudflare';
    }
}
