<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use notwonderful\FilamentCloudflare\Models\CloudflareSetting;
use notwonderful\FilamentCloudflare\Services\CloudflareSettingsService;
use Orchestra\Testbench\TestCase;

class CloudflareSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            \notwonderful\FilamentCloudflare\CloudflareServiceProvider::class,
        ];
    }

    public function test_get_returns_setting_value_when_exists(): void
    {
        CloudflareSetting::create([
            'key' => 'cloudflare_zone_id',
            'value' => 'test-zone-id-123',
        ]);

        $service = new CloudflareSettingsService();
        $result = $service->get('cloudflare_zone_id');

        $this->assertEquals('test-zone-id-123', $result);
    }

    public function test_get_returns_default_when_setting_not_exists(): void
    {
        $service = new CloudflareSettingsService();
        $result = $service->get('non_existent_key', 'default-value');

        $this->assertEquals('default-value', $result);
    }

    public function test_set_creates_setting_when_not_exists(): void
    {
        $this->assertNull(CloudflareSetting::where('key', 'cloudflare_zone_id')->first());

        $service = new CloudflareSettingsService();
        $service->set('cloudflare_zone_id', 'new-zone-id');

        $setting = CloudflareSetting::where('key', 'cloudflare_zone_id')->first();
        $this->assertNotNull($setting);
        $this->assertEquals('new-zone-id', $setting->value);
    }

    public function test_set_updates_setting_when_exists(): void
    {
        CloudflareSetting::create([
            'key' => 'cloudflare_zone_id',
            'value' => 'old-zone-id',
        ]);

        $service = new CloudflareSettingsService();
        $service->set('cloudflare_zone_id', 'new-zone-id');

        $settings = CloudflareSetting::where('key', 'cloudflare_zone_id')->get();
        $this->assertCount(1, $settings);
        $this->assertEquals('new-zone-id', $settings->first()->value);
    }

    public function test_getAll_returns_all_settings_in_correct_format(): void
    {
        CloudflareSetting::create(['key' => 'cloudflare_email', 'value' => 'test@example.com']);
        CloudflareSetting::create(['key' => 'cloudflare_zone_id', 'value' => 'zone-123']);
        CloudflareSetting::create(['key' => 'cloudflare_account_id', 'value' => 'account-456']);

        $service = new CloudflareSettingsService();
        $result = $service->getAll();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('api_key', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('zone_id', $result);
        $this->assertArrayHasKey('account_id', $result);

        $this->assertEquals('test@example.com', $result['email']);
        $this->assertEquals('zone-123', $result['zone_id']);
        $this->assertEquals('account-456', $result['account_id']);
        $this->assertNull($result['api_key']);
        $this->assertNull($result['token']);
    }

    public function test_setAll_saves_all_settings(): void
    {
        $service = new CloudflareSettingsService();
        $service->setAll([
            'email' => 'test@example.com',
            'zone_id' => 'zone-123',
            'account_id' => 'account-456',
        ]);

        $this->assertEquals('test@example.com', $service->get('cloudflare_email'));
        $this->assertEquals('zone-123', $service->get('cloudflare_zone_id'));
        $this->assertEquals('account-456', $service->get('cloudflare_account_id'));
    }
}
