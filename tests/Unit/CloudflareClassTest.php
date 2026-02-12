<?php

declare(strict_types=1);

namespace Tests\Unit;

use Mockery;
use notwonderful\FilamentCloudflare\Cloudflare;
use notwonderful\FilamentCloudflare\CloudflareServiceProvider;
use notwonderful\FilamentCloudflare\Contracts\CloudflareAuthInterface;
use notwonderful\FilamentCloudflare\Contracts\CloudflareClientInterface;
use notwonderful\FilamentCloudflare\Contracts\CloudflareSettingsInterface;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareException;
use notwonderful\FilamentCloudflare\Http\CloudflareResponse;
use notwonderful\FilamentCloudflare\Services\Cache\CloudflareCacheService;
use notwonderful\FilamentCloudflare\Services\CacheRules\CloudflareCacheRulesService;
use notwonderful\FilamentCloudflare\Services\Firewall\CloudflareFirewallService;
use notwonderful\FilamentCloudflare\Services\PageRules\CloudflarePageRulesService;
use notwonderful\FilamentCloudflare\Services\Zone\CloudflareZoneService;
use Orchestra\Testbench\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class CloudflareClassTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            CloudflareServiceProvider::class,
        ];
    }

    private function makeResponse(array $data): CloudflareResponse
    {
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->once()->andReturn(json_encode($data));

        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getBody')->once()->andReturn($stream);

        return new CloudflareResponse($response);
    }

    private function createCloudflare(
        ?CloudflareClientInterface $client = null,
        ?CloudflareSettingsInterface $settings = null,
        ?CloudflareAuthInterface $auth = null,
    ): Cloudflare {
        return new Cloudflare(
            $client ?? Mockery::mock(CloudflareClientInterface::class),
            $settings ?? Mockery::mock(CloudflareSettingsInterface::class),
            $auth ?? Mockery::mock(CloudflareAuthInterface::class),
        );
    }

    // --- verifyCredentials ---

    public function test_verifyCredentials_succeeds_with_valid_token(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'user/tokens/verify')
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['status' => 'active']]));

        $auth = Mockery::mock(CloudflareAuthInterface::class);
        $auth->shouldReceive('getToken')->andReturn('valid-token');

        $cf = $this->createCloudflare(client: $client, auth: $auth);
        $this->assertTrue($cf->verifyCredentials());
    }

    public function test_verifyCredentials_falls_back_to_user_endpoint(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);

        // Token verify fails
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'user/tokens/verify')
            ->andReturn($this->makeResponse([
                'success' => false,
                'errors' => [['message' => 'Invalid token']],
            ]));

        // Fallback to /user succeeds
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'user')
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'user-1']]));

        $auth = Mockery::mock(CloudflareAuthInterface::class);
        $auth->shouldReceive('getToken')->andReturn('some-token');

        $cf = $this->createCloudflare(client: $client, auth: $auth);
        $this->assertTrue($cf->verifyCredentials());
    }

    public function test_verifyCredentials_skips_token_verify_when_no_token(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);

        // Should only call /user, never /user/tokens/verify
        $client->shouldNotReceive('makeRequest')->with('GET', 'user/tokens/verify', Mockery::any());
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'user')
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'user-1']]));

        $auth = Mockery::mock(CloudflareAuthInterface::class);
        $auth->shouldReceive('getToken')->andReturn(null);

        $cf = $this->createCloudflare(client: $client, auth: $auth);
        $this->assertTrue($cf->verifyCredentials());
    }

    public function test_verifyCredentials_throws_when_both_methods_fail(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);

        $client->shouldReceive('makeRequest')
            ->with('GET', 'user/tokens/verify')
            ->andReturn($this->makeResponse([
                'success' => false,
                'errors' => [['message' => 'Invalid token']],
            ]));

        $client->shouldReceive('makeRequest')
            ->with('GET', 'user')
            ->andReturn($this->makeResponse([
                'success' => false,
                'errors' => [['message' => 'Authentication error']],
            ]));

        $auth = Mockery::mock(CloudflareAuthInterface::class);
        $auth->shouldReceive('getToken')->andReturn('bad-token');

        $cf = $this->createCloudflare(client: $client, auth: $auth);

        $this->expectException(CloudflareException::class);
        $cf->verifyCredentials();
    }

    // --- settings accessor ---

    public function test_settings_returns_settings_interface(): void
    {
        $settings = Mockery::mock(CloudflareSettingsInterface::class);
        $cf = $this->createCloudflare(settings: $settings);

        $this->assertSame($settings, $cf->settings());
    }

    // --- getZoneId / getAccountId ---

    public function test_getZoneId_delegates_to_settings(): void
    {
        $settings = Mockery::mock(CloudflareSettingsInterface::class);
        $settings->shouldReceive('get')->with('cloudflare_zone_id')->andReturn('zone-abc');

        $cf = $this->createCloudflare(settings: $settings);
        $this->assertEquals('zone-abc', $cf->getZoneId());
    }

    public function test_getAccountId_delegates_to_settings(): void
    {
        $settings = Mockery::mock(CloudflareSettingsInterface::class);
        $settings->shouldReceive('get')->with('cloudflare_account_id')->andReturn('acct-123');

        $cf = $this->createCloudflare(settings: $settings);
        $this->assertEquals('acct-123', $cf->getAccountId());
    }

    // --- service accessors (lazy caching) ---

    public function test_zone_returns_zone_service_instance(): void
    {
        $cf = $this->createCloudflare();
        $service = $cf->zone();

        $this->assertInstanceOf(CloudflareZoneService::class, $service);
        // Verify lazy caching: same instance returned on second call
        $this->assertSame($service, $cf->zone());
    }

    public function test_cache_returns_cache_service_instance(): void
    {
        $cf = $this->createCloudflare();
        $service = $cf->cache();

        $this->assertInstanceOf(CloudflareCacheService::class, $service);
        $this->assertSame($service, $cf->cache());
    }

    public function test_firewall_returns_firewall_service_instance(): void
    {
        $cf = $this->createCloudflare();
        $service = $cf->firewall();

        $this->assertInstanceOf(CloudflareFirewallService::class, $service);
        $this->assertSame($service, $cf->firewall());
    }

    public function test_cacheRules_returns_cache_rules_service_instance(): void
    {
        $cf = $this->createCloudflare();
        $service = $cf->cacheRules();

        $this->assertInstanceOf(CloudflareCacheRulesService::class, $service);
        $this->assertSame($service, $cf->cacheRules());
    }

    public function test_pageRules_returns_page_rules_service_instance(): void
    {
        $cf = $this->createCloudflare();
        $service = $cf->pageRules();

        $this->assertInstanceOf(CloudflarePageRulesService::class, $service);
        $this->assertSame($service, $cf->pageRules());
    }
}
