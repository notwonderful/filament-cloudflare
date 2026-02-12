<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Services;

use notwonderful\FilamentCloudflare\Contracts\CloudflareSettingsInterface;
use notwonderful\FilamentCloudflare\Models\CloudflareSetting;

class CloudflareSettingsService implements CloudflareSettingsInterface
{
    private const array KEY_MAP = [
        'email' => 'cloudflare_email',
        'api_key' => 'cloudflare_api_key',
        'token' => 'cloudflare_token',
        'zone_id' => 'cloudflare_zone_id',
        'account_id' => 'cloudflare_account_id',
    ];

    private const array CONFIG_MAP = [
        'cloudflare_email' => 'cloudflare.email',
        'cloudflare_api_key' => 'cloudflare.api_key',
        'cloudflare_token' => 'cloudflare.token',
        'cloudflare_zone_id' => 'cloudflare.zone_id',
        'cloudflare_account_id' => 'cloudflare.account_id',
    ];

    /** @var array<string, string|null>|null */
    private ?array $cache = null;

    public function get(string $key, ?string $default = null): ?string
    {
        $configValue = $this->getFromConfig($key);

        if ($configValue !== null) {
            return $configValue;
        }

        return $this->getAllFromDb()[$key] ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        CloudflareSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        $this->cache = null;
    }

    public function getAll(): array
    {
        return array_map(function ($dbKey) {
            return $this->get($dbKey);
        }, self::KEY_MAP);
    }

    public function setAll(array $settings): void
    {
        foreach ($settings as $key => $value) {
            if (isset(self::KEY_MAP[$key])) {
                $this->set(self::KEY_MAP[$key], $value);
            }
        }
    }

    public function flush(): void
    {
        $this->cache = null;
    }

    private function getFromConfig(string $dbKey): ?string
    {
        $configKey = self::CONFIG_MAP[$dbKey] ?? null;

        if ($configKey === null) {
            return null;
        }

        $value = config($configKey);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return array<string, string|null> */
    private function getAllFromDb(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        return $this->cache = CloudflareSetting::all()
            ->pluck('value', 'key')
            ->toArray();
    }
}
