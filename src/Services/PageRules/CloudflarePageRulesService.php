<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Services\PageRules;

use notwonderful\FilamentCloudflare\Enums\PageRuleStatus;
use notwonderful\FilamentCloudflare\Services\Base\CloudflareBaseService;

class CloudflarePageRulesService extends CloudflareBaseService
{
    /** @return array<int, array<string, mixed>> */
    public function getPageRules(?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);

        return $this->remember("page_rules:{$zoneId}", function () use ($zoneId) {
            $response = $this->client->makeRequest('GET', "zones/{$zoneId}/pagerules");
            $response->throwIfFailed();
            return $response->getResult() ?? [];
        });
    }

    /** @return array<string, mixed> */
    public function getPageRule(string $ruleId, ?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);

        return $this->remember("page_rule:{$zoneId}:{$ruleId}", function () use ($zoneId, $ruleId) {
            $response = $this->client->makeRequest('GET', "zones/{$zoneId}/pagerules/{$ruleId}");
            $response->throwIfFailed();
            return $response->getResult() ?? [];
        });
    }

    /**
     * @param array<int, array<string, mixed>> $targets
     * @param array<int, array<string, mixed>> $actions
     * @return array<string, mixed>
     */
    public function createPageRule(
        array $targets,
        array $actions,
        int $priority = 1,
        PageRuleStatus $status = PageRuleStatus::Active,
        ?string $zoneId = null
    ): array {
        $zoneId = $this->ensureZoneId($zoneId);

        if (empty($targets)) {
            throw new \InvalidArgumentException('Page rule targets must not be empty.');
        }

        $data = [
            'targets' => $targets,
            'actions' => $actions,
            'priority' => $priority,
            'status' => $status->value,
        ];

        $response = $this->client->makeRequest('POST', "zones/{$zoneId}/pagerules", [
            'json' => $data,
        ]);
        $response->throwIfFailed();
        $this->invalidateCache("page_rules:{$zoneId}");
        return $response->getResult() ?? [];
    }

    /**
     * @param array<int, array<string, mixed>> $targets
     * @param array<int, array<string, mixed>> $actions
     * @return array<string, mixed>
     */
    public function updatePageRule(
        string $ruleId,
        array $targets,
        array $actions,
        int $priority,
        PageRuleStatus $status,
        ?string $zoneId = null
    ): array {
        $zoneId = $this->ensureZoneId($zoneId);

        if (empty($targets)) {
            throw new \InvalidArgumentException('Page rule targets must not be empty.');
        }

        $data = [
            'targets' => $targets,
            'actions' => $actions,
            'priority' => $priority,
            'status' => $status->value,
        ];

        $response = $this->client->makeRequest('PUT', "zones/{$zoneId}/pagerules/{$ruleId}", [
            'json' => $data,
        ]);
        $response->throwIfFailed();
        $this->invalidateCache("page_rules:{$zoneId}");
        $this->invalidateCache("page_rule:{$zoneId}:{$ruleId}");
        return $response->getResult() ?? [];
    }

    /** @return array<string, mixed> */
    public function deletePageRule(string $ruleId, ?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);
        $response = $this->client->makeRequest('DELETE', "zones/{$zoneId}/pagerules/{$ruleId}");
        $response->throwIfFailed();
        $this->invalidateCache("page_rules:{$zoneId}");
        $this->invalidateCache("page_rule:{$zoneId}:{$ruleId}");
        return $response->getResult() ?? [];
    }
}
