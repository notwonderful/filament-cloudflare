<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Services\Firewall;

use notwonderful\FilamentCloudflare\Enums\FirewallMode;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareApiException;
use notwonderful\FilamentCloudflare\Http\CloudflarePaginatedResult;
use notwonderful\FilamentCloudflare\Services\Base\CloudflareBaseService;

class CloudflareFirewallService extends CloudflareBaseService
{
    /** @return array<string, mixed> */
    public function getFirewallRules(?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);

        return $this->remember("firewall_rules:{$zoneId}", function () use ($zoneId) {
            try {
                $response = $this->client->makeRequest('GET', "zones/{$zoneId}/rulesets/phases/http_request_firewall_custom/entrypoint");
                $response->throwIfFailed();
                return $response->getResult() ?? [];
            } catch (CloudflareApiException $e) {
                return match (true) {
                    str_contains($e->getMessage(), '404'),
                    str_contains($e->getMessage(), 'not found') => ['result' => null, 'rules' => []],
                    default => throw $e,
                };
            }
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function getFirewallAccessRules(int $page = 1, int $perPage = 50, ?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);

        return $this->remember("firewall_access_rules:{$zoneId}", function () use ($zoneId, $page, $perPage) {
            $response = $this->client->makeRequest('GET', "zones/{$zoneId}/firewall/access_rules/rules", [
                'query' => [
                    'page' => $page,
                    'per_page' => $perPage,
                ],
            ]);
            $response->throwIfFailed();
            return $response->getResult() ?? [];
        }, suffix: "{$page}:{$perPage}");
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public function createFirewallAccessRule(
        FirewallMode $mode,
        array $configuration,
        ?string $notes = null,
        ?string $zoneId = null
    ): array {
        $zoneId = $this->ensureZoneId($zoneId);

        if (!isset($configuration['target'], $configuration['value'])) {
            throw new \InvalidArgumentException('Configuration must contain "target" and "value" keys.');
        }

        $data = array_filter([
            'mode' => $mode->value,
            'configuration' => $configuration,
            'notes' => $notes,
        ], fn ($v) => $v !== null);

        $response = $this->client->makeRequest('POST', "zones/{$zoneId}/firewall/access_rules/rules", [
            'json' => $data,
        ]);
        $response->throwIfFailed();
        $this->invalidateCache("firewall_access_rules:{$zoneId}");
        return $response->getResult() ?? [];
    }

    /** @return array<string, mixed> */
    public function deleteFirewallAccessRule(string $ruleId, ?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);
        $response = $this->client->makeRequest('DELETE', "zones/{$zoneId}/firewall/access_rules/rules/{$ruleId}");
        $response->throwIfFailed();
        $this->invalidateCache("firewall_access_rules:{$zoneId}");
        return $response->getResult() ?? [];
    }

    public function getFirewallUserAgentRules(int $page = 1, int $perPage = 1000, ?string $zoneId = null): CloudflarePaginatedResult
    {
        $zoneId = $this->ensureZoneId($zoneId);

        return $this->remember("firewall_ua_rules:{$zoneId}", function () use ($zoneId, $page, $perPage): CloudflarePaginatedResult {
            $response = $this->client->makeRequest('GET', "zones/{$zoneId}/firewall/ua_rules", [
                'query' => [
                    'page' => $page,
                    'per_page' => $perPage,
                ],
            ]);
            $response->throwIfFailed();

            return new CloudflarePaginatedResult(
                items: $response->getResult() ?? [],
                resultInfo: $response->getResultInfo(),
            );
        }, suffix: "{$page}:{$perPage}");
    }

    /** @return array<string, mixed> */
    public function createFirewallUserAgentRule(
        string $userAgent,
        FirewallMode $mode,
        ?string $description = null,
        ?string $zoneId = null
    ): array {
        $zoneId = $this->ensureZoneId($zoneId);

        if (trim($userAgent) === '') {
            throw new \InvalidArgumentException('User agent string must not be empty.');
        }

        $data = array_filter([
            'mode' => $mode->value,
            'configuration' => [
                'target' => 'ua',
                'value' => $userAgent,
            ],
            'description' => $description,
        ], fn ($v) => $v !== null);

        $response = $this->client->makeRequest('POST', "zones/{$zoneId}/firewall/ua_rules", [
            'json' => $data,
        ]);
        $response->throwIfFailed();
        $this->invalidateCache("firewall_ua_rules:{$zoneId}");
        return $response->getResult() ?? [];
    }

    /** @return array<string, mixed> */
    public function updateFirewallUserAgentRule(
        string $ruleId,
        FirewallMode $mode,
        string $userAgent,
        ?string $description = null,
        bool $paused = false,
        ?string $zoneId = null
    ): array {
        $zoneId = $this->ensureZoneId($zoneId);

        if (trim($userAgent) === '') {
            throw new \InvalidArgumentException('User agent string must not be empty.');
        }

        $data = array_filter([
            'id' => $ruleId,
            'mode' => $mode->value,
            'configuration' => [
                'target' => 'ua',
                'value' => $userAgent,
            ],
            'paused' => $paused,
            'description' => $description,
        ], fn ($v) => $v !== null);

        $response = $this->client->makeRequest('PUT', "zones/{$zoneId}/firewall/ua_rules/{$ruleId}", [
            'json' => $data,
        ]);
        $response->throwIfFailed();
        $this->invalidateCache("firewall_ua_rules:{$zoneId}");
        return $response->getResult() ?? [];
    }

    /** @return array<string, mixed> */
    public function deleteFirewallUserAgentRule(string $ruleId, ?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);
        $response = $this->client->makeRequest('DELETE', "zones/{$zoneId}/firewall/ua_rules/{$ruleId}");
        $response->throwIfFailed();
        $this->invalidateCache("firewall_ua_rules:{$zoneId}");
        return $response->getResult() ?? [];
    }
}
