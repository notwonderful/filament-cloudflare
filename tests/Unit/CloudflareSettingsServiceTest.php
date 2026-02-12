<?php

declare(strict_types=1);

namespace Tests\Unit;

use notwonderful\FilamentCloudflare\Services\CloudflareSettingsService;
use Orchestra\Testbench\TestCase;

class CloudflareSettingsServiceTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            \notwonderful\FilamentCloudflare\CloudflareServiceProvider::class,
        ];
    }

    public function test_get_returns_config_value_when_set(): void
    {
        config(['cloudflare.zone_id' => 'zone-from-config']);

        $service = new CloudflareSettingsService();

        $this->assertEquals('zone-from-config', $service->get('cloudflare_zone_id'));
    }

    public function test_get_returns_default_when_config_not_set(): void
    {
        config(['cloudflare.zone_id' => null]);

        $service = new CloudflareSettingsService();

        $this->assertEquals('fallback', $service->get('cloudflare_zone_id', 'fallback'));
    }

    public function test_get_returns_default_when_config_is_empty_string(): void
    {
        config(['cloudflare.zone_id' => '']);

        $service = new CloudflareSettingsService();

        $this->assertEquals('fallback', $service->get('cloudflare_zone_id', 'fallback'));
    }

    public function test_get_returns_default_for_unknown_key(): void
    {
        $service = new CloudflareSettingsService();

        $this->assertNull($service->get('unknown_key'));
        $this->assertEquals('default', $service->get('unknown_key', 'default'));
    }

    public function test_getAll_returns_all_settings_from_config(): void
    {
        config([
            'cloudflare.email' => 'test@example.com',
            'cloudflare.api_key' => null,
            'cloudflare.token' => 'test-token',
            'cloudflare.zone_id' => 'zone-123',
            'cloudflare.account_id' => 'account-456',
        ]);

        $service = new CloudflareSettingsService();
        $result = $service->getAll();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('api_key', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('zone_id', $result);
        $this->assertArrayHasKey('account_id', $result);

        $this->assertEquals('test@example.com', $result['email']);
        $this->assertNull($result['api_key']);
        $this->assertEquals('test-token', $result['token']);
        $this->assertEquals('zone-123', $result['zone_id']);
        $this->assertEquals('account-456', $result['account_id']);
    }

    public function test_getAll_returns_nulls_when_config_empty(): void
    {
        config([
            'cloudflare.email' => null,
            'cloudflare.api_key' => null,
            'cloudflare.token' => null,
            'cloudflare.zone_id' => null,
            'cloudflare.account_id' => null,
        ]);

        $service = new CloudflareSettingsService();
        $result = $service->getAll();

        foreach ($result as $value) {
            $this->assertNull($value);
        }
    }
}
