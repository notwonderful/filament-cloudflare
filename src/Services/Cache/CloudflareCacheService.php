<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Services\Cache;

use notwonderful\FilamentCloudflare\Services\Base\CloudflareBaseService;

class CloudflareCacheService extends CloudflareBaseService
{
    /**
     * @param array<int, string>|null $files
     * @param array<int, string>|null $tags
     * @param array<int, string>|null $hosts
     * @return array<string, mixed>
     */
    public function purgeCache(
        bool $purgeEverything = true,
        ?array $files = null,
        ?array $tags = null,
        ?array $hosts = null,
        ?string $zoneId = null
    ): array {
        $zoneId = $this->ensureZoneId($zoneId);

        $data = match ($purgeEverything) {
            true => ['purge_everything' => true],
            false => array_filter([
                'files' => $files,
                'tags' => $tags,
                'hosts' => $hosts,
            ], fn($v) => $v !== null),
        };

        $response = $this->client->makeRequest('POST', "zones/{$zoneId}/purge_cache", [
            'json' => $data,
        ]);
        $response->throwIfFailed();
        return $response->getResult() ?? [];
    }
}
