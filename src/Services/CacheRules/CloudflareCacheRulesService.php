<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Services\CacheRules;

use notwonderful\FilamentCloudflare\Exceptions\CloudflareApiException;
use notwonderful\FilamentCloudflare\Services\Base\CloudflareBaseService;

class CloudflareCacheRulesService extends CloudflareBaseService
{
    /** @return array<string, mixed> */
    public function getCacheRules(?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);

        return $this->remember("cache_rules:{$zoneId}", function () use ($zoneId) {
            try {
                $response = $this->client->makeRequest('GET', "zones/{$zoneId}/rulesets/phases/http_request_cache_settings/entrypoint");
                $response->throwIfFailed();
                return $response->getResult() ?? [];
            } catch (CloudflareApiException $e) {
                // 10003 = "could not find entrypoint" — no ruleset exists yet for this phase
                if ($e->hasErrorCode(10003)) {
                    return ['rules' => []];
                }
                throw $e;
            }
        });
    }

    /**
     * @param array<string, mixed> $actionParameters
     * @return array<string, mixed>
     */
    public function createCacheRule(
        string $description,
        string $expression,
        array $actionParameters,
        ?string $rulesetId = null,
        bool $enabled = true,
        ?string $zoneId = null
    ): array {
        $zoneId = $this->ensureZoneId($zoneId);

        if (trim($expression) === '') {
            throw new \InvalidArgumentException('Cache rule expression must not be empty.');
        }

        $data = [
            'action' => 'set_cache_settings',
            'description' => $description,
            'expression' => $expression,
            'action_parameters' => $actionParameters,
            'enabled' => $enabled,
        ];

        $response = match ($rulesetId) {
            null => $this->client->makeRequest('PUT', "zones/{$zoneId}/rulesets/phases/http_request_cache_settings/entrypoint", [
                'json' => ['rules' => [$data]],
            ]),
            default => $this->client->makeRequest('POST', "zones/{$zoneId}/rulesets/{$rulesetId}/rules", [
                'json' => $data,
            ]),
        };

        $response->throwIfFailed();
        $this->invalidateCache("cache_rules:{$zoneId}");
        return $response->getResult() ?? [];
    }

    /**
     * @param array<string, mixed> $actionParameters
     * @return array<string, mixed>
     */
    public function updateCacheRule(
        string $rulesetId,
        string $ruleId,
        string $description,
        string $expression,
        array $actionParameters,
        bool $enabled,
        ?string $zoneId = null
    ): array {
        $zoneId = $this->ensureZoneId($zoneId);

        if (trim($expression) === '') {
            throw new \InvalidArgumentException('Cache rule expression must not be empty.');
        }

        $data = [
            'id' => $ruleId,
            'action' => 'set_cache_settings',
            'description' => $description,
            'expression' => $expression,
            'action_parameters' => $actionParameters,
            'enabled' => $enabled,
        ];

        $response = $this->client->makeRequest('PATCH', "zones/{$zoneId}/rulesets/{$rulesetId}/rules/{$ruleId}", [
            'json' => $data,
        ]);
        $response->throwIfFailed();
        $this->invalidateCache("cache_rules:{$zoneId}");
        return $response->getResult() ?? [];
    }

    /** @return array<string, mixed> */
    public function deleteCacheRule(string $rulesetId, string $ruleId, ?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);
        $response = $this->client->makeRequest('DELETE', "zones/{$zoneId}/rulesets/{$rulesetId}/rules/{$ruleId}");
        $response->throwIfFailed();
        $this->invalidateCache("cache_rules:{$zoneId}");
        return $response->getResult() ?? [];
    }
}
