<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Services\Zone;

use notwonderful\FilamentCloudflare\Services\Base\CloudflareBaseService;

class CloudflareZoneService extends CloudflareBaseService
{
    /** @return array<int, array<string, mixed>> */
    public function listZones(): array
    {
        return $this->remember('zones', function () {
            $response = $this->client->makeRequest('GET', 'zones');
            $response->throwIfFailed();
            return $response->getResult() ?? [];
        });
    }

    /** @return array<string, mixed> */
    public function getZoneDetails(?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);

        return $this->remember("zone_details:{$zoneId}", function () use ($zoneId) {
            $response = $this->client->makeRequest('GET', "zones/{$zoneId}");
            $response->throwIfFailed();
            return $response->getResult() ?? [];
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function getZoneSettings(?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);

        return $this->remember("zone_settings:{$zoneId}", function () use ($zoneId) {
            $response = $this->client->makeRequest('GET', "zones/{$zoneId}/settings");
            $response->throwIfFailed();
            return $response->getResult() ?? [];
        });
    }

    /** @return array<string, mixed> */
    public function updateZoneSetting(string $setting, mixed $value, ?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);
        $response = $this->client->makeRequest('PATCH', "zones/{$zoneId}/settings/{$setting}", [
            'json' => ['value' => $value],
        ]);
        $response->throwIfFailed();
        $this->invalidateCache("zone_settings:{$zoneId}");
        return $response->getResult() ?? [];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public function updateZoneSettings(array $settings, ?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);

        $items = [];
        foreach ($settings as $id => $setting) {
            $items[] = [
                'id' => $id,
                'value' => $setting['value'] ?? $setting,
            ];
        }

        $response = $this->client->makeRequest('PATCH', "zones/{$zoneId}/settings", [
            'json' => ['items' => $items],
        ]);
        $response->throwIfFailed();
        $this->invalidateCache("zone_settings:{$zoneId}");
        return $response->getResult() ?? [];
    }
}
