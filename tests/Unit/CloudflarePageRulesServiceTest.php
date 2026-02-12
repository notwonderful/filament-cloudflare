<?php

declare(strict_types=1);

namespace Tests\Unit;

use Mockery;
use notwonderful\FilamentCloudflare\Contracts\CloudflareClientInterface;
use notwonderful\FilamentCloudflare\Contracts\CloudflareSettingsInterface;
use notwonderful\FilamentCloudflare\Enums\PageRuleStatus;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareApiException;
use notwonderful\FilamentCloudflare\Http\CloudflareResponse;
use notwonderful\FilamentCloudflare\Services\PageRules\CloudflarePageRulesService;
use Orchestra\Testbench\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class CloudflarePageRulesServiceTest extends TestCase
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

    public function test_getPageRules_returns_all_rules(): void
    {
        $rules = [
            ['id' => 'pr-1', 'status' => 'active'],
            ['id' => 'pr-2', 'status' => 'disabled'],
        ];

        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'zones/zone-123/pagerules')
            ->andReturn($this->makeResponse(['success' => true, 'result' => $rules]));

        $service = new CloudflarePageRulesService($client, $this->mockSettings());
        $this->assertEquals($rules, $service->getPageRules());
    }

    public function test_getPageRule_returns_single_rule(): void
    {
        $rule = ['id' => 'pr-1', 'status' => 'active', 'targets' => []];

        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'zones/zone-123/pagerules/pr-1')
            ->andReturn($this->makeResponse(['success' => true, 'result' => $rule]));

        $service = new CloudflarePageRulesService($client, $this->mockSettings());
        $this->assertEquals($rule, $service->getPageRule('pr-1'));
    }

    public function test_createPageRule_sends_correct_payload(): void
    {
        $targets = [['target' => 'url', 'constraint' => ['operator' => 'matches', 'value' => '*.example.com/*']]];
        $actions = [['id' => 'forwarding_url', 'value' => ['url' => 'https://new.example.com', 'status_code' => 301]]];

        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('POST', 'zones/zone-123/pagerules', Mockery::on(
                fn (array $opts) => $opts['json']['targets'] === $targets
                    && $opts['json']['actions'] === $actions
                    && $opts['json']['priority'] === 5
                    && $opts['json']['status'] === 'active'
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'pr-new']]));

        $service = new CloudflarePageRulesService($client, $this->mockSettings());
        $result = $service->createPageRule($targets, $actions, priority: 5);

        $this->assertEquals(['id' => 'pr-new'], $result);
    }

    public function test_createPageRule_defaults_to_active_status(): void
    {
        $targets = [['target' => 'url', 'constraint' => ['operator' => 'matches', 'value' => '*']]];

        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('POST', 'zones/zone-123/pagerules', Mockery::on(
                fn (array $opts) => $opts['json']['status'] === 'active'
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'pr-new']]));

        $service = new CloudflarePageRulesService($client, $this->mockSettings());
        $result = $service->createPageRule($targets, []);

        $this->assertEquals(['id' => 'pr-new'], $result);
    }

    public function test_updatePageRule_sends_put_request(): void
    {
        $targets = [['target' => 'url', 'constraint' => ['operator' => 'matches', 'value' => '*.example.com/*']]];
        $actions = [['id' => 'always_online', 'value' => 'on']];

        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('PUT', 'zones/zone-123/pagerules/pr-1', Mockery::on(
                fn (array $opts) => $opts['json']['targets'] === $targets
                    && $opts['json']['actions'] === $actions
                    && $opts['json']['priority'] === 2
                    && $opts['json']['status'] === 'disabled'
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'pr-1']]));

        $service = new CloudflarePageRulesService($client, $this->mockSettings());
        $result = $service->updatePageRule('pr-1', $targets, $actions, 2, PageRuleStatus::Disabled);

        $this->assertEquals(['id' => 'pr-1'], $result);
    }

    public function test_deletePageRule_sends_delete_request(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('DELETE', 'zones/zone-123/pagerules/pr-1')
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'pr-1']]));

        $service = new CloudflarePageRulesService($client, $this->mockSettings());
        $result = $service->deletePageRule('pr-1');

        $this->assertEquals(['id' => 'pr-1'], $result);
    }

    public function test_createPageRule_validates_empty_targets(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $service = new CloudflarePageRulesService($client, $this->mockSettings());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page rule targets must not be empty');

        $service->createPageRule([], []);
    }

    public function test_throws_on_api_error(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->andReturn($this->makeResponse([
                'success' => false,
                'errors' => [['code' => 1004, 'message' => 'Invalid zone']],
            ]));

        $this->expectException(CloudflareApiException::class);

        $service = new CloudflarePageRulesService($client, $this->mockSettings());
        $service->getPageRules();
    }
}
