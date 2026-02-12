<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Services\Access;

use notwonderful\FilamentCloudflare\Exceptions\CloudflareConfigurationException;
use notwonderful\FilamentCloudflare\Services\Base\CloudflareBaseService;

/**
 * Manages Cloudflare Access Apps, Groups, and Identity Providers.
 */
class CloudflareAccessService extends CloudflareBaseService
{
    /**
     * @return array<int, array<string, mixed>>
     * @throws CloudflareConfigurationException
     */
    public function getAccessApps(): array
    {
        $accountId = $this->ensureAccountId();

        return $this->remember("access_apps:{$accountId}", function () use ($accountId) {
            $response = $this->client->makeRequest('GET', "accounts/{$accountId}/access/apps");
            $response->throwIfFailed();
            $result = $response->getResult();

            return is_array($result) ? $result : [];
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws CloudflareConfigurationException
     */
    public function getAccessGroups(): array
    {
        $accountId = $this->ensureAccountId();

        return $this->remember("access_groups:{$accountId}", function () use ($accountId) {
            $response = $this->client->makeRequest('GET', "accounts/{$accountId}/access/groups");
            $response->throwIfFailed();
            $result = $response->getResult();

            return is_array($result) ? $result : [];
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws CloudflareConfigurationException
     */
    public function getAccessIdentityProviders(): array
    {
        $accountId = $this->ensureAccountId();

        return $this->remember("access_idps:{$accountId}", function () use ($accountId) {
            $response = $this->client->makeRequest('GET', "accounts/{$accountId}/access/identity_providers");
            $response->throwIfFailed();
            $result = $response->getResult();

            return is_array($result) ? $result : [];
        });
    }

    /**
     * @param string $type 'admin' or 'install'
     * @return array<string, mixed>
     * @throws CloudflareConfigurationException
     */
    public function createAdminAccessApp(string $type = 'admin', ?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);
        $accountId = $this->ensureAccountId();

        $identityProviders = $this->getAccessIdentityProviders();
        if (empty($identityProviders)) {
            throw new CloudflareConfigurationException(
                'No Cloudflare Access login methods configured. Please configure at least one identity provider in Cloudflare Zero Trust dashboard.'
            );
        }

        $zoneDetails = $this->client->makeRequest('GET', "zones/{$zoneId}");
        $zoneDetails->throwIfFailed();
        $zone = $zoneDetails->getResult();
        $domain = $zone['name'] ?? '';

        $applicationUrl = match ($type) {
            'admin' => "https://{$domain}/admin",
            'install' => "https://{$domain}/install",
            default => "https://{$domain}/admin",
        };

        $parsedUrl = parse_url($applicationUrl);
        $hostPath = ($parsedUrl['host'] ?? $domain) . ($parsedUrl['path'] ?? '');

        $payload = [
            'name' => "Laravel {$type} protection",
            'type' => 'self_hosted',
            'destinations' => [
                [
                    'type' => 'public',
                    'uri' => $hostPath,
                ],
            ],
            'session_duration' => '24h',
            'policies' => [
                [
                    'decision' => 'allow',
                    'name' => "Allow {$type} access",
                    'include' => [
                        [
                            'email' => [
                                'email' => '*',
                            ],
                        ],
                    ],
                    'exclude' => [],
                    'require' => [],
                ],
            ],
        ];

        $response = $this->client->makeRequest('POST', "accounts/{$accountId}/access/apps", [
            'json' => $payload,
        ]);
        $response->throwIfFailed();
        $this->invalidateCache("access_apps:{$accountId}");

        return $response->getResult() ?? [];
    }

    /**
     * @return array<string, mixed>
     * @throws CloudflareConfigurationException
     */
    public function deleteAccessApp(string $appId): array
    {
        $accountId = $this->ensureAccountId();

        $response = $this->client->makeRequest('DELETE', "accounts/{$accountId}/access/apps/{$appId}");
        $response->throwIfFailed();
        $this->invalidateCache("access_apps:{$accountId}");

        return $response->getResult() ?? [];
    }
}
