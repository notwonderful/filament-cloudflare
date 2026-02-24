<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare;

use notwonderful\FilamentCloudflare\Contracts\CloudflareAuthInterface;
use notwonderful\FilamentCloudflare\Contracts\CloudflareClientInterface;
use notwonderful\FilamentCloudflare\Contracts\CloudflareSettingsInterface;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareApiException;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareException;
use notwonderful\FilamentCloudflare\Services\Access\CloudflareAccessService;
use notwonderful\FilamentCloudflare\Services\Analytics\CloudflareAnalyticsService;
use notwonderful\FilamentCloudflare\Services\Cache\CloudflareCacheService;
use notwonderful\FilamentCloudflare\Services\CacheRules\CloudflareCacheRulesService;
use notwonderful\FilamentCloudflare\Services\Dns\CloudflareDnsService;
use notwonderful\FilamentCloudflare\Services\EdgeCaching\CloudflareEdgeCachingService;
use notwonderful\FilamentCloudflare\Services\Firewall\CloudflareFirewallService;
use notwonderful\FilamentCloudflare\Services\GraphQL\CloudflareGraphQLService;
use notwonderful\FilamentCloudflare\Services\PageRules\CloudflarePageRulesService;
use notwonderful\FilamentCloudflare\Services\Zone\CloudflareZoneService;

class Cloudflare
{
    private ?CloudflareZoneService $zoneService = null;
    private ?CloudflareCacheService $cacheService = null;
    private ?CloudflareDnsService $dnsService = null;
    private ?CloudflareFirewallService $firewallService = null;
    private ?CloudflareCacheRulesService $cacheRulesService = null;
    private ?CloudflarePageRulesService $pageRulesService = null;
    private ?CloudflareAnalyticsService $analyticsService = null;
    private ?CloudflareAccessService $accessService = null;
    private ?CloudflareEdgeCachingService $edgeCachingService = null;

    public function __construct(
        private readonly CloudflareClientInterface $client,
        private readonly CloudflareSettingsInterface $settings,
        private readonly CloudflareAuthInterface $auth,
    ) {}

    public function zone(): CloudflareZoneService
    {
        return $this->zoneService ??= new CloudflareZoneService($this->client, $this->settings);
    }

    public function cache(): CloudflareCacheService
    {
        return $this->cacheService ??= new CloudflareCacheService($this->client, $this->settings);
    }

    public function dns(): CloudflareDnsService
    {
        return $this->dnsService ??= new CloudflareDnsService($this->client, $this->settings);
    }

    public function firewall(): CloudflareFirewallService
    {
        return $this->firewallService ??= new CloudflareFirewallService($this->client, $this->settings);
    }

    public function cacheRules(): CloudflareCacheRulesService
    {
        return $this->cacheRulesService ??= new CloudflareCacheRulesService($this->client, $this->settings);
    }

    public function pageRules(): CloudflarePageRulesService
    {
        return $this->pageRulesService ??= new CloudflarePageRulesService($this->client, $this->settings);
    }

    public function analytics(): CloudflareAnalyticsService
    {
        return $this->analyticsService ??= new CloudflareAnalyticsService(
            $this->client,
            $this->settings,
            new CloudflareGraphQLService($this->client),
        );
    }

    public function access(): CloudflareAccessService
    {
        return $this->accessService ??= new CloudflareAccessService($this->client, $this->settings);
    }

    public function edgeCaching(): CloudflareEdgeCachingService
    {
        return $this->edgeCachingService ??= new CloudflareEdgeCachingService(
            $this->client,
            $this->settings,
            $this->cacheRules(),
        );
    }

    public function settings(): CloudflareSettingsInterface
    {
        return $this->settings;
    }

    /**
     * Verify API credentials.
     *
     * @return true if credentials are valid
     * @throws CloudflareException with details when verification fails
     */
    public function verifyCredentials(): bool
    {
        $lastException = null;

        if (! empty($this->auth->getToken())) {
            try {
                $response = $this->client->makeRequest('GET', 'user/tokens/verify');
                $response->throwIfFailed();

                return true;
            } catch (CloudflareException $e) {
                $lastException = $e;
            }
        }

        try {
            $response = $this->client->makeRequest('GET', 'user');
            $response->throwIfFailed();

            return true;
        } catch (CloudflareException $e) {
            $lastException = $e;
        }

        throw new CloudflareApiException(
            $lastException->getMessage(),
            [],
            0,
            $lastException,
        );
    }

    public function getZoneId(): ?string
    {
        return $this->settings->get('cloudflare_zone_id');
    }

    public function getAccountId(): ?string
    {
        return $this->settings->get('cloudflare_account_id');
    }
}
