<?php

declare(strict_types=1);

namespace Tests\Unit;

use Mockery;
use notwonderful\FilamentCloudflare\Contracts\CloudflareClientInterface;
use notwonderful\FilamentCloudflare\Contracts\CloudflareSettingsInterface;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareApiException;
use notwonderful\FilamentCloudflare\Http\CloudflareResponse;
use notwonderful\FilamentCloudflare\Services\CacheRules\CloudflareCacheRulesService;
use Orchestra\Testbench\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class CloudflareCacheRulesServiceTest extends TestCase
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

    // --- getCacheRules ---

    public function test_getCacheRules_returns_result(): void
    {
        $rulesetData = [
            'id' => 'ruleset-1',
            'rules' => [
                ['id' => 'rule-1', 'description' => 'Cache API', 'enabled' => true],
            ],
        ];

        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'zones/zone-123/rulesets/phases/http_request_cache_settings/entrypoint')
            ->andReturn($this->makeResponse(['success' => true, 'result' => $rulesetData]));

        $service = new CloudflareCacheRulesService($client, $this->mockSettings());
        $this->assertEquals($rulesetData, $service->getCacheRules());
    }

    public function test_getCacheRules_returns_empty_on_404(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->andReturn($this->makeResponse([
                'success' => false,
                'errors' => [['code' => 10003, 'message' => 'could not find entrypoint ruleset for phase']],
            ]));

        $service = new CloudflareCacheRulesService($client, $this->mockSettings());
        $result = $service->getCacheRules();

        $this->assertArrayHasKey('rules', $result);
        $this->assertEmpty($result['rules']);
    }

    public function test_getCacheRules_rethrows_non_404_errors(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->andReturn($this->makeResponse([
                'success' => false,
                'errors' => [['code' => 1003, 'message' => 'Invalid zone identifier']],
            ]));

        $this->expectException(CloudflareApiException::class);
        $this->expectExceptionMessage('Invalid zone identifier');

        $service = new CloudflareCacheRulesService($client, $this->mockSettings());
        $service->getCacheRules();
    }

    // --- createCacheRule ---

    public function test_createCacheRule_uses_put_when_no_ruleset_id(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('PUT', 'zones/zone-123/rulesets/phases/http_request_cache_settings/entrypoint', Mockery::on(
                fn (array $opts) => isset($opts['json']['rules'][0])
                    && $opts['json']['rules'][0]['action'] === 'set_cache_settings'
                    && $opts['json']['rules'][0]['description'] === 'Cache everything'
                    && $opts['json']['rules'][0]['expression'] === '(true)'
                    && $opts['json']['rules'][0]['enabled'] === true
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'ruleset-new']]));

        $service = new CloudflareCacheRulesService($client, $this->mockSettings());
        $result = $service->createCacheRule('Cache everything', '(true)', ['cache' => true]);

        $this->assertEquals(['id' => 'ruleset-new'], $result);
    }

    public function test_createCacheRule_uses_post_when_ruleset_id_provided(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('POST', 'zones/zone-123/rulesets/rs-1/rules', Mockery::on(
                fn (array $opts) => $opts['json']['action'] === 'set_cache_settings'
                    && $opts['json']['description'] === 'Add rule'
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'rule-new']]));

        $service = new CloudflareCacheRulesService($client, $this->mockSettings());
        $result = $service->createCacheRule('Add rule', '(true)', ['cache' => true], rulesetId: 'rs-1');

        $this->assertEquals(['id' => 'rule-new'], $result);
    }

    // --- updateCacheRule ---

    public function test_updateCacheRule_sends_patch_request(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('PATCH', 'zones/zone-123/rulesets/rs-1/rules/rule-1', Mockery::on(
                fn (array $opts) => $opts['json']['id'] === 'rule-1'
                    && $opts['json']['description'] === 'Updated'
                    && $opts['json']['enabled'] === false
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'rule-1']]));

        $service = new CloudflareCacheRulesService($client, $this->mockSettings());
        $result = $service->updateCacheRule('rs-1', 'rule-1', 'Updated', '(true)', ['cache' => true], false);

        $this->assertEquals(['id' => 'rule-1'], $result);
    }

    // --- deleteCacheRule ---

    public function test_deleteCacheRule_sends_delete_request(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('DELETE', 'zones/zone-123/rulesets/rs-1/rules/rule-1')
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'rule-1']]));

        $service = new CloudflareCacheRulesService($client, $this->mockSettings());
        $result = $service->deleteCacheRule('rs-1', 'rule-1');

        $this->assertEquals(['id' => 'rule-1'], $result);
    }
}
