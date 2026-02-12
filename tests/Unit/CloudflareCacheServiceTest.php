<?php

declare(strict_types=1);

namespace Tests\Unit;

use Mockery;
use notwonderful\FilamentCloudflare\Contracts\CloudflareClientInterface;
use notwonderful\FilamentCloudflare\Contracts\CloudflareSettingsInterface;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareApiException;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareConfigurationException;
use notwonderful\FilamentCloudflare\Http\CloudflareResponse;
use notwonderful\FilamentCloudflare\Services\Cache\CloudflareCacheService;
use Orchestra\Testbench\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class CloudflareCacheServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function createCloudflareResponse(array $data): CloudflareResponse
    {
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')
            ->once()
            ->andReturn(json_encode($data));

        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getBody')
            ->once()
            ->andReturn($stream);

        return new CloudflareResponse($response);
    }

    protected function mockSettings(string $zoneId = 'test-zone-id-123'): CloudflareSettingsInterface
    {
        $mock = Mockery::mock(CloudflareSettingsInterface::class);
        $mock->shouldReceive('get')
            ->with('cloudflare_zone_id')
            ->andReturn($zoneId);

        return $mock;
    }

    public function test_purges_everything(): void
    {
        $mockClient = Mockery::mock(CloudflareClientInterface::class);
        $mockClient->shouldReceive('makeRequest')
            ->once()
            ->with('POST', 'zones/test-zone-id-123/purge_cache', Mockery::on(
                fn (array $opts) => ($opts['json']['purge_everything'] ?? false) === true
            ))
            ->andReturn($this->createCloudflareResponse([
                'success' => true,
                'result' => ['id' => 'purge-123'],
            ]));

        $service = new CloudflareCacheService($mockClient, $this->mockSettings());
        $result = $service->purgeCache(purgeEverything: true);

        $this->assertEquals(['id' => 'purge-123'], $result);
    }

    public function test_purges_specific_files(): void
    {
        $files = ['/file1.css', '/file2.js'];

        $mockClient = Mockery::mock(CloudflareClientInterface::class);
        $mockClient->shouldReceive('makeRequest')
            ->once()
            ->with('POST', 'zones/test-zone-id-123/purge_cache', Mockery::on(
                fn (array $opts) => ($opts['json']['files'] ?? []) === $files
                    && ! isset($opts['json']['purge_everything'])
            ))
            ->andReturn($this->createCloudflareResponse([
                'success' => true,
                'result' => ['id' => 'purge-123'],
            ]));

        $service = new CloudflareCacheService($mockClient, $this->mockSettings());
        $result = $service->purgeCache(purgeEverything: false, files: $files);

        $this->assertEquals(['id' => 'purge-123'], $result);
    }

    public function test_purges_tags_and_hosts(): void
    {
        $tags = ['tag1'];
        $hosts = ['example.com'];

        $mockClient = Mockery::mock(CloudflareClientInterface::class);
        $mockClient->shouldReceive('makeRequest')
            ->once()
            ->with('POST', 'zones/test-zone-id-123/purge_cache', Mockery::on(
                fn (array $opts) => ($opts['json']['tags'] ?? []) === $tags
                    && ($opts['json']['hosts'] ?? []) === $hosts
            ))
            ->andReturn($this->createCloudflareResponse([
                'success' => true,
                'result' => ['id' => 'purge-123'],
            ]));

        $service = new CloudflareCacheService($mockClient, $this->mockSettings());
        $result = $service->purgeCache(purgeEverything: false, tags: $tags, hosts: $hosts);

        $this->assertEquals(['id' => 'purge-123'], $result);
    }

    public function test_filters_null_values(): void
    {
        $files = ['/file1.css'];

        $mockClient = Mockery::mock(CloudflareClientInterface::class);
        $mockClient->shouldReceive('makeRequest')
            ->once()
            ->with('POST', 'zones/test-zone-id-123/purge_cache', Mockery::on(
                fn (array $opts) => isset($opts['json']['files'])
                    && ! isset($opts['json']['tags'])
                    && ! isset($opts['json']['hosts'])
            ))
            ->andReturn($this->createCloudflareResponse([
                'success' => true,
                'result' => ['id' => 'purge-123'],
            ]));

        $service = new CloudflareCacheService($mockClient, $this->mockSettings());
        $result = $service->purgeCache(purgeEverything: false, files: $files);

        $this->assertEquals(['id' => 'purge-123'], $result);
    }

    public function test_uses_provided_zone_id(): void
    {
        $mockClient = Mockery::mock(CloudflareClientInterface::class);
        $mockClient->shouldReceive('makeRequest')
            ->once()
            ->with('POST', 'zones/custom-zone/purge_cache', Mockery::any())
            ->andReturn($this->createCloudflareResponse([
                'success' => true,
                'result' => ['id' => 'purge-123'],
            ]));

        $service = new CloudflareCacheService($mockClient, $this->mockSettings());
        $result = $service->purgeCache(purgeEverything: true, zoneId: 'custom-zone');

        $this->assertEquals(['id' => 'purge-123'], $result);
    }

    public function test_throws_when_zone_id_missing(): void
    {
        $mockSettings = Mockery::mock(CloudflareSettingsInterface::class);
        $mockSettings->shouldReceive('get')
            ->with('cloudflare_zone_id')
            ->andReturn(null);

        $mockClient = Mockery::mock(CloudflareClientInterface::class);

        $this->expectException(CloudflareConfigurationException::class);
        $this->expectExceptionMessage('Zone ID is not configured');

        $service = new CloudflareCacheService($mockClient, $mockSettings);
        $service->purgeCache(purgeEverything: true);
    }

    public function test_throws_on_api_error(): void
    {
        $mockClient = Mockery::mock(CloudflareClientInterface::class);
        $mockClient->shouldReceive('makeRequest')
            ->once()
            ->andReturn($this->createCloudflareResponse([
                'success' => false,
                'errors' => [['code' => 1004, 'message' => 'Invalid zone ID']],
                'result' => null,
            ]));

        $this->expectException(CloudflareApiException::class);

        $service = new CloudflareCacheService($mockClient, $this->mockSettings());
        $service->purgeCache(purgeEverything: true);
    }
}
