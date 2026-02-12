<?php

declare(strict_types=1);

namespace Tests\Unit;

use Mockery;
use notwonderful\FilamentCloudflare\Contracts\CloudflareClientInterface;
use notwonderful\FilamentCloudflare\Contracts\CloudflareSettingsInterface;
use notwonderful\FilamentCloudflare\Enums\FirewallMode;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareApiException;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareConfigurationException;
use notwonderful\FilamentCloudflare\Http\CloudflarePaginatedResult;
use notwonderful\FilamentCloudflare\Http\CloudflareResponse;
use notwonderful\FilamentCloudflare\Services\Firewall\CloudflareFirewallService;
use Orchestra\Testbench\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class CloudflareFirewallServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('cloudflare.cache.ttl', 0);
        $app['config']->set('cloudflare.cache.prefix', 'cloudflare');
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

    // --- getFirewallAccessRules ---

    public function test_getFirewallAccessRules_returns_result(): void
    {
        $rules = [
            ['id' => 'rule-1', 'mode' => 'block'],
            ['id' => 'rule-2', 'mode' => 'whitelist'],
        ];

        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'zones/zone-123/firewall/access_rules/rules', Mockery::on(
                fn (array $opts) => $opts['query']['page'] === 1 && $opts['query']['per_page'] === 50
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => $rules]));

        $service = new CloudflareFirewallService($client, $this->mockSettings());
        $this->assertEquals($rules, $service->getFirewallAccessRules());
    }

    public function test_getFirewallAccessRules_passes_pagination_params(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'zones/zone-123/firewall/access_rules/rules', Mockery::on(
                fn (array $opts) => $opts['query']['page'] === 3 && $opts['query']['per_page'] === 25
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => []]));

        $service = new CloudflareFirewallService($client, $this->mockSettings());
        $result = $service->getFirewallAccessRules(page: 3, perPage: 25);

        $this->assertEmpty($result);
    }

    public function test_getFirewallAccessRules_throws_when_no_zone_id(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $service = new CloudflareFirewallService($client, $this->mockSettings(''));

        $this->expectException(CloudflareConfigurationException::class);
        $service->getFirewallAccessRules();
    }

    // --- createFirewallAccessRule ---

    public function test_createFirewallAccessRule_sends_correct_payload(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('POST', 'zones/zone-123/firewall/access_rules/rules', Mockery::on(
                fn (array $opts) => $opts['json']['mode'] === 'block'
                    && $opts['json']['configuration'] === ['target' => 'ip', 'value' => '1.2.3.4']
                    && $opts['json']['notes'] === 'test note'
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'new-rule']]));

        $service = new CloudflareFirewallService($client, $this->mockSettings());
        $result = $service->createFirewallAccessRule(
            FirewallMode::Block,
            ['target' => 'ip', 'value' => '1.2.3.4'],
            'test note',
        );

        $this->assertEquals(['id' => 'new-rule'], $result);
    }

    public function test_createFirewallAccessRule_filters_null_notes(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('POST', 'zones/zone-123/firewall/access_rules/rules', Mockery::on(
                fn (array $opts) => !isset($opts['json']['notes'])
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'rule']]));

        $service = new CloudflareFirewallService($client, $this->mockSettings());
        $result = $service->createFirewallAccessRule(FirewallMode::Block, ['target' => 'ip', 'value' => '1.2.3.4']);

        $this->assertEquals(['id' => 'rule'], $result);
    }

    // --- deleteFirewallAccessRule ---

    public function test_deleteFirewallAccessRule_sends_delete_request(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('DELETE', 'zones/zone-123/firewall/access_rules/rules/rule-99')
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'rule-99']]));

        $service = new CloudflareFirewallService($client, $this->mockSettings());
        $result = $service->deleteFirewallAccessRule('rule-99');

        $this->assertEquals(['id' => 'rule-99'], $result);
    }

    // --- getFirewallUserAgentRules ---

    public function test_getFirewallUserAgentRules_returns_paginated_result(): void
    {
        $fullResponse = [
            'success' => true,
            'result' => [['id' => 'ua-1', 'mode' => 'block']],
            'result_info' => ['page' => 1, 'total_count' => 1, 'total_pages' => 1],
        ];

        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'zones/zone-123/firewall/ua_rules', Mockery::any())
            ->andReturn($this->makeResponse($fullResponse));

        $service = new CloudflareFirewallService($client, $this->mockSettings());
        $result = $service->getFirewallUserAgentRules();

        $this->assertInstanceOf(CloudflarePaginatedResult::class, $result);
        $this->assertCount(1, $result->items);
        $this->assertEquals('ua-1', $result->items[0]['id']);
        $this->assertEquals(1, $result->totalPages());
        $this->assertEquals(1, $result->totalCount());
    }

    // --- createFirewallUserAgentRule ---

    public function test_createFirewallUserAgentRule_sends_correct_payload(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('POST', 'zones/zone-123/firewall/ua_rules', Mockery::on(
                fn (array $opts) => $opts['json']['mode'] === 'block'
                    && $opts['json']['configuration']['target'] === 'ua'
                    && $opts['json']['configuration']['value'] === 'BadBot/1.0'
                    && $opts['json']['description'] === 'Block bad bot'
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'ua-new']]));

        $service = new CloudflareFirewallService($client, $this->mockSettings());
        $result = $service->createFirewallUserAgentRule('BadBot/1.0', FirewallMode::Block, 'Block bad bot');

        $this->assertEquals(['id' => 'ua-new'], $result);
    }

    // --- updateFirewallUserAgentRule ---

    public function test_updateFirewallUserAgentRule_sends_put_request(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('PUT', 'zones/zone-123/firewall/ua_rules/ua-1', Mockery::on(
                fn (array $opts) => $opts['json']['id'] === 'ua-1'
                    && $opts['json']['mode'] === 'challenge'
                    && $opts['json']['configuration']['value'] === 'NewBot/2.0'
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'ua-1']]));

        $service = new CloudflareFirewallService($client, $this->mockSettings());
        $result = $service->updateFirewallUserAgentRule('ua-1', FirewallMode::Challenge, 'NewBot/2.0');

        $this->assertEquals(['id' => 'ua-1'], $result);
    }

    // --- deleteFirewallUserAgentRule ---

    public function test_deleteFirewallUserAgentRule_sends_delete_request(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('DELETE', 'zones/zone-123/firewall/ua_rules/ua-1')
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'ua-1']]));

        $service = new CloudflareFirewallService($client, $this->mockSettings());
        $result = $service->deleteFirewallUserAgentRule('ua-1');

        $this->assertEquals(['id' => 'ua-1'], $result);
    }

    // --- getFirewallRules (custom rules) ---

    public function test_getFirewallRules_returns_result_on_success(): void
    {
        $rulesetData = [
            'id' => 'ruleset-1',
            'rules' => [['id' => 'rule-1', 'expression' => 'ip.src eq 1.2.3.4']],
        ];

        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'zones/zone-123/rulesets/phases/http_request_firewall_custom/entrypoint')
            ->andReturn($this->makeResponse(['success' => true, 'result' => $rulesetData]));

        $service = new CloudflareFirewallService($client, $this->mockSettings());
        $result = $service->getFirewallRules();

        $this->assertEquals($rulesetData, $result);
    }

    public function test_getFirewallRules_returns_empty_on_404(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->andReturn($this->makeResponse([
                'success' => false,
                'errors' => [['code' => 404, 'message' => 'Ruleset not found']],
            ]));

        $service = new CloudflareFirewallService($client, $this->mockSettings());
        $result = $service->getFirewallRules();

        $this->assertArrayHasKey('rules', $result);
        $this->assertEmpty($result['rules']);
    }

    // --- input validation ---

    public function test_createFirewallAccessRule_validates_configuration(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $service = new CloudflareFirewallService($client, $this->mockSettings());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Configuration must contain "target" and "value" keys');

        $service->createFirewallAccessRule(FirewallMode::Block, ['bad' => 'data']);
    }

    public function test_createFirewallUserAgentRule_validates_empty_user_agent(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $service = new CloudflareFirewallService($client, $this->mockSettings());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User agent string must not be empty');

        $service->createFirewallUserAgentRule('', FirewallMode::Block);
    }

    // --- custom zone ID override ---

    public function test_uses_custom_zone_id_when_provided(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'zones/custom-zone/firewall/access_rules/rules', Mockery::any())
            ->andReturn($this->makeResponse(['success' => true, 'result' => []]));

        $service = new CloudflareFirewallService($client, $this->mockSettings());
        $result = $service->getFirewallAccessRules(zoneId: 'custom-zone');

        $this->assertEmpty($result);
    }
}
