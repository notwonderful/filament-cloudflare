<?php

declare(strict_types=1);

namespace Tests\Unit;

use Mockery;
use notwonderful\FilamentCloudflare\Contracts\CloudflareClientInterface;
use notwonderful\FilamentCloudflare\Contracts\CloudflareSettingsInterface;
use notwonderful\FilamentCloudflare\Http\CloudflareResponse;
use notwonderful\FilamentCloudflare\Services\CacheRules\CloudflareCacheRulesService;
use notwonderful\FilamentCloudflare\Services\EdgeCaching\CloudflareEdgeCachingService;
use Orchestra\Testbench\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class CloudflareEdgeCachingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('cloudflare.cache.ttl', 0);
    }

    private function makeResponse(array $data): CloudflareResponse
    {
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->once()->andReturn(json_encode($data));

        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getBody')->once()->andReturn($stream);

        return new CloudflareResponse($response);
    }

    private function mockSettings(string $zoneId = 'zone-123'): CloudflareSettingsInterface
    {
        $mock = Mockery::mock(CloudflareSettingsInterface::class);
        $mock->shouldReceive('get')->with('cloudflare_zone_id')->andReturn($zoneId);

        return $mock;
    }

    private function guestExpression(): string
    {
        return '(not http.cookie contains "laravel_session=" and not http.cookie contains "XSRF-TOKEN=" and http.request.method eq "GET" and http.request.uri.query eq "")';
    }

    // --- enableGuestCache ---

    public function test_enableGuestCache_delegates_to_cache_rules_service(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $settings = $this->mockSettings();

        $cacheRulesService = Mockery::mock(CloudflareCacheRulesService::class);
        $cacheRulesService->shouldReceive('createCacheRule')
            ->once()
            ->with(
                'Cache guest pages',
                $this->guestExpression(),
                Mockery::on(fn (array $action) =>
                    $action['cache'] === true
                    && $action['edge_ttl']['default'] === 3600
                    && $action['browser_ttl']['default'] === 3600
                ),
                Mockery::any(), // rulesetId not passed (null)
                Mockery::any(), // enabled not passed (true)
                'zone-123',    // zoneId
            )
            ->andReturn(['id' => 'new-rule']);

        $service = new CloudflareEdgeCachingService($client, $settings, $cacheRulesService);
        $result = $service->enableGuestCache(3600);

        $this->assertEquals(['id' => 'new-rule'], $result);
    }

    // --- isGuestCacheEnabled ---

    public function test_isGuestCacheEnabled_returns_true_when_rule_exists(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $settings = $this->mockSettings();

        $cacheRulesService = Mockery::mock(CloudflareCacheRulesService::class);
        $cacheRulesService->shouldReceive('getCacheRules')
            ->once()
            ->with('zone-123')
            ->andReturn([
                'id' => 'ruleset-1',
                'rules' => [
                    ['id' => 'rule-1', 'expression' => $this->guestExpression()],
                ],
            ]);

        $service = new CloudflareEdgeCachingService($client, $settings, $cacheRulesService);
        $this->assertTrue($service->isGuestCacheEnabled());
    }

    public function test_isGuestCacheEnabled_returns_false_when_rule_absent(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $settings = $this->mockSettings();

        $cacheRulesService = Mockery::mock(CloudflareCacheRulesService::class);
        $cacheRulesService->shouldReceive('getCacheRules')
            ->once()
            ->with('zone-123')
            ->andReturn([
                'id' => 'ruleset-1',
                'rules' => [
                    ['id' => 'rule-1', 'expression' => '(some other expression)'],
                ],
            ]);

        $service = new CloudflareEdgeCachingService($client, $settings, $cacheRulesService);
        $this->assertFalse($service->isGuestCacheEnabled());
    }

    public function test_isGuestCacheEnabled_returns_false_when_no_rules(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $settings = $this->mockSettings();

        $cacheRulesService = Mockery::mock(CloudflareCacheRulesService::class);
        $cacheRulesService->shouldReceive('getCacheRules')
            ->once()
            ->with('zone-123')
            ->andReturn(['id' => 'ruleset-1', 'rules' => []]);

        $service = new CloudflareEdgeCachingService($client, $settings, $cacheRulesService);
        $this->assertFalse($service->isGuestCacheEnabled());
    }

    // --- disableGuestCache ---

    public function test_disableGuestCache_deletes_matching_rule(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $settings = $this->mockSettings();

        $cacheRulesService = Mockery::mock(CloudflareCacheRulesService::class);
        $cacheRulesService->shouldReceive('getCacheRules')
            ->once()
            ->with('zone-123')
            ->andReturn([
                'id' => 'ruleset-1',
                'rules' => [
                    ['id' => 'rule-1', 'expression' => $this->guestExpression()],
                ],
            ]);

        $cacheRulesService->shouldReceive('deleteCacheRule')
            ->once()
            ->with('ruleset-1', 'rule-1', 'zone-123');

        $service = new CloudflareEdgeCachingService($client, $settings, $cacheRulesService);
        $service->disableGuestCache();

        // Verify deleteCacheRule was called (Mockery expectation)
        $this->assertTrue(true);
    }

    public function test_disableGuestCache_does_nothing_when_rule_not_found(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $settings = $this->mockSettings();

        $cacheRulesService = Mockery::mock(CloudflareCacheRulesService::class);
        $cacheRulesService->shouldReceive('getCacheRules')
            ->with('zone-123')
            ->andReturn(['id' => 'ruleset-1', 'rules' => []]);

        $cacheRulesService->shouldNotReceive('deleteCacheRule');

        $service = new CloudflareEdgeCachingService($client, $settings, $cacheRulesService);
        $service->disableGuestCache();

        $this->assertFalse($service->isGuestCacheEnabled());
    }

    // --- enableMediaCache ---

    public function test_enableMediaCache_uses_default_storage_prefix(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $settings = $this->mockSettings();

        $cacheRulesService = Mockery::mock(CloudflareCacheRulesService::class);
        $cacheRulesService->shouldReceive('createCacheRule')
            ->once()
            ->with(
                'Cache media attachments',
                Mockery::on(fn (string $expr) => str_contains($expr, '/storage')),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                'zone-123',
            )
            ->andReturn(['id' => 'media-rule']);

        $service = new CloudflareEdgeCachingService($client, $settings, $cacheRulesService);
        $result = $service->enableMediaCache(86400);

        $this->assertEquals(['id' => 'media-rule'], $result);
    }

    public function test_enableMediaCache_uses_custom_prefix(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $settings = $this->mockSettings();

        $cacheRulesService = Mockery::mock(CloudflareCacheRulesService::class);
        $cacheRulesService->shouldReceive('createCacheRule')
            ->once()
            ->with(
                'Cache media attachments',
                Mockery::on(fn (string $expr) => str_contains($expr, '/media')),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                'zone-123',
            )
            ->andReturn(['id' => 'media-rule']);

        $service = new CloudflareEdgeCachingService($client, $settings, $cacheRulesService);
        $result = $service->enableMediaCache(86400, mediaPathPrefix: '/media');

        $this->assertEquals(['id' => 'media-rule'], $result);
    }

    // --- isMediaCacheEnabled ---

    public function test_isMediaCacheEnabled_detects_matching_rule(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $settings = $this->mockSettings();

        // Build expected media expression with default /storage prefix
        $service = new CloudflareEdgeCachingService($client, $settings, Mockery::mock(CloudflareCacheRulesService::class));

        // Use reflection to get the media expression
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('mediaExpression');
        $method->setAccessible(true);
        $mediaExpr = $method->invoke($service, '/storage');

        $cacheRulesService = Mockery::mock(CloudflareCacheRulesService::class);
        $cacheRulesService->shouldReceive('getCacheRules')
            ->once()
            ->with('zone-123')
            ->andReturn([
                'id' => 'ruleset-1',
                'rules' => [
                    ['id' => 'rule-1', 'expression' => $mediaExpr],
                ],
            ]);

        $service = new CloudflareEdgeCachingService($client, $settings, $cacheRulesService);
        $this->assertTrue($service->isMediaCacheEnabled());
    }
}
