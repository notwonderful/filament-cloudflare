<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Services\Base;

use Illuminate\Support\Facades\Cache;
use notwonderful\FilamentCloudflare\Contracts\CloudflareClientInterface;
use notwonderful\FilamentCloudflare\Contracts\CloudflareSettingsInterface;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareConfigurationException;

/**
 * Provides shared zone/account ID resolution, and version-based cache
 * invalidation that works reliably with every Laravel cache driver.
 */
abstract class CloudflareBaseService
{
    protected ?string $zoneId = null;
    protected ?string $accountId = null;

    public function __construct(
        protected readonly CloudflareClientInterface $client,
        protected readonly CloudflareSettingsInterface $settings
    ) {}

    protected function getDefaultZoneId(): ?string
    {
        return $this->zoneId ?? ($this->zoneId = $this->settings->get('cloudflare_zone_id'));
    }

    /**
     * @throws CloudflareConfigurationException
     */
    protected function ensureZoneId(?string $zoneId = null): string
    {
        $zoneId ??= $this->getDefaultZoneId();
        return $zoneId ?: throw CloudflareConfigurationException::missingZoneId();
    }

    protected function getAccountId(): ?string
    {
        if ($this->accountId !== null) {
            return $this->accountId;
        }
        return $this->accountId = $this->settings->get('cloudflare_account_id');
    }

    /**
     * @throws CloudflareConfigurationException
     */
    protected function ensureAccountId(): string
    {
        $accountId = $this->getAccountId();
        return $accountId ?: throw CloudflareConfigurationException::missingAccountId();
    }

    /**
     * Cache an API response using a version-based key.
     *
     * The cache key includes a group version number. When the group is
     * invalidated, the version increments and all previous entries become
     * stale (they expire naturally via TTL — no need to track individual keys).
     *
     * @param string $group  Logical group name (e.g. "firewall_access_rules:zone-123")
     * @param string $suffix Optional key suffix for variants within the group (e.g. pagination)
     */
    protected function remember(string $group, \Closure $callback, string $suffix = ''): mixed
    {
        $ttl = (int) config('cloudflare.cache.ttl', 300);

        if ($ttl <= 0) {
            return $callback();
        }

        $prefix = config('cloudflare.cache.prefix', 'cloudflare');
        $version = (int) Cache::get("{$prefix}:v:{$group}", 0);
        $fullKey = "{$prefix}:{$group}:v{$version}" . ($suffix !== '' ? ":{$suffix}" : '');

        return Cache::remember($fullKey, $ttl, $callback);
    }

    /**
     * Invalidate all cached entries in a group by incrementing its version.
     *
     * Old cache entries will expire naturally via TTL — no need to
     * enumerate or track individual keys. Works with every cache driver.
     */
    protected function invalidateCache(string $group): void
    {
        $prefix = config('cloudflare.cache.prefix', 'cloudflare');
        $versionKey = "{$prefix}:v:{$group}";
        $current = (int) Cache::get($versionKey, 0);
        Cache::put($versionKey, $current + 1, 86400);
    }
}
