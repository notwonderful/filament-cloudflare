<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Services\Analytics;

use notwonderful\FilamentCloudflare\Contracts\CloudflareClientInterface;
use notwonderful\FilamentCloudflare\Contracts\CloudflareSettingsInterface;
use notwonderful\FilamentCloudflare\Services\Base\CloudflareBaseService;
use notwonderful\FilamentCloudflare\Services\GraphQL\CloudflareGraphQLService;

class CloudflareAnalyticsService extends CloudflareBaseService
{
    /**
     * @param CloudflareClientInterface $client HTTP client
     * @param CloudflareSettingsInterface $settings Settings service
     * @param CloudflareGraphQLService $graphQLService GraphQL service
     */
    public function __construct(
        CloudflareClientInterface $client,
        CloudflareSettingsInterface $settings,
        protected readonly CloudflareGraphQLService $graphQLService
    ) {
        parent::__construct($client, $settings);
    }

    /** @return array<string, mixed> */
    public function getGraphQLAnalytics(int $days = 1, ?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);

        return $this->graphQLService->getZoneAnalytics($zoneId, $days);
    }

    /** @return array<string, mixed> */
    public function getGraphQLCaptchaSolveRate(string $ruleId, int $days = 1, ?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);

        return $this->graphQLService->getCaptchaSolveRate($zoneId, $ruleId, $days);
    }

    /** @return array<string, mixed> */
    public function getGraphQLRuleActivity(string $ruleId, int $days = 1, ?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);

        return $this->graphQLService->getRuleActivity($zoneId, $ruleId, $days);
    }
}
