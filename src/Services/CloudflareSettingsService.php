<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Services;

use notwonderful\FilamentCloudflare\Contracts\CloudflareSettingsInterface;

class CloudflareSettingsService implements CloudflareSettingsInterface
{
    private const array CONFIG_MAP = [
        'cloudflare_email' => 'cloudflare.email',
        'cloudflare_api_key' => 'cloudflare.api_key',
        'cloudflare_token' => 'cloudflare.token',
        'cloudflare_zone_id' => 'cloudflare.zone_id',
        'cloudflare_account_id' => 'cloudflare.account_id',
    ];

    private const array KEY_MAP = [
        'email' => 'cloudflare_email',
        'api_key' => 'cloudflare_api_key',
        'token' => 'cloudflare_token',
        'zone_id' => 'cloudflare_zone_id',
        'account_id' => 'cloudflare_account_id',
    ];

    public function get(string $key, ?string $default = null): ?string
    {
        $configKey = self::CONFIG_MAP[$key] ?? null;

        if ($configKey === null) {
            return $default;
        }

        $value = config($configKey);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /** @return array<string, string|null> */
    public function getAll(): array
    {
        return array_map(
            fn (string $dbKey) => $this->get($dbKey),
            self::KEY_MAP,
        );
    }
}
