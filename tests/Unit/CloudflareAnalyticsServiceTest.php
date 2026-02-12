<?php

declare(strict_types=1);

namespace Tests\Unit;

use Mockery;
use notwonderful\FilamentCloudflare\Contracts\CloudflareClientInterface;
use notwonderful\FilamentCloudflare\Contracts\CloudflareSettingsInterface;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareConfigurationException;
use notwonderful\FilamentCloudflare\Services\Analytics\CloudflareAnalyticsService;
use notwonderful\FilamentCloudflare\Services\GraphQL\CloudflareGraphQLService;
use Orchestra\Testbench\TestCase;

class CloudflareAnalyticsServiceTest extends TestCase
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

    private function mockSettings(string $zoneId = 'zone-123'): CloudflareSettingsInterface
    {
        $mock = Mockery::mock(CloudflareSettingsInterface::class);
        $mock->shouldReceive('get')->with('cloudflare_zone_id')->andReturn($zoneId);

        return $mock;
    }

    // --- getGraphQLAnalytics ---

    public function test_getGraphQLAnalytics_delegates_to_graphql_service(): void
    {
        $analyticsData = [
            'viewer' => [
                'zones' => [
                    ['totals' => [['uniq' => ['uniques' => 100]]]],
                ],
            ],
        ];

        $client = Mockery::mock(CloudflareClientInterface::class);
        $graphql = Mockery::mock(CloudflareGraphQLService::class);
        $graphql->shouldReceive('getZoneAnalytics')
            ->once()
            ->with('zone-123', 1)
            ->andReturn($analyticsData);

        $service = new CloudflareAnalyticsService($client, $this->mockSettings(), $graphql);
        $result = $service->getGraphQLAnalytics();

        $this->assertEquals($analyticsData, $result);
    }

    public function test_getGraphQLAnalytics_passes_custom_days(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $graphql = Mockery::mock(CloudflareGraphQLService::class);
        $graphql->shouldReceive('getZoneAnalytics')
            ->once()
            ->with('zone-123', 7)
            ->andReturn([]);

        $service = new CloudflareAnalyticsService($client, $this->mockSettings(), $graphql);
        $result = $service->getGraphQLAnalytics(7);

        $this->assertEmpty($result);
    }

    public function test_getGraphQLAnalytics_uses_custom_zone_id(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $graphql = Mockery::mock(CloudflareGraphQLService::class);
        $graphql->shouldReceive('getZoneAnalytics')
            ->once()
            ->with('custom-zone', 1)
            ->andReturn([]);

        $service = new CloudflareAnalyticsService($client, $this->mockSettings(), $graphql);
        $result = $service->getGraphQLAnalytics(zoneId: 'custom-zone');

        $this->assertEmpty($result);
    }

    public function test_getGraphQLAnalytics_throws_when_no_zone_id(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $graphql = Mockery::mock(CloudflareGraphQLService::class);
        $service = new CloudflareAnalyticsService($client, $this->mockSettings(''), $graphql);

        $this->expectException(CloudflareConfigurationException::class);
        $service->getGraphQLAnalytics();
    }

    // --- getGraphQLCaptchaSolveRate ---

    public function test_getGraphQLCaptchaSolveRate_delegates_correctly(): void
    {
        $rateData = [
            'viewer' => [
                'zones' => [
                    ['issued' => [['count' => 50]], 'solved' => [['count' => 45]]],
                ],
            ],
        ];

        $client = Mockery::mock(CloudflareClientInterface::class);
        $graphql = Mockery::mock(CloudflareGraphQLService::class);
        $graphql->shouldReceive('getCaptchaSolveRate')
            ->once()
            ->with('zone-123', 'rule-abc', 1)
            ->andReturn($rateData);

        $service = new CloudflareAnalyticsService($client, $this->mockSettings(), $graphql);
        $result = $service->getGraphQLCaptchaSolveRate('rule-abc');

        $this->assertEquals($rateData, $result);
    }

    public function test_getGraphQLCaptchaSolveRate_passes_custom_days(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $graphql = Mockery::mock(CloudflareGraphQLService::class);
        $graphql->shouldReceive('getCaptchaSolveRate')
            ->once()
            ->with('zone-123', 'rule-abc', 30)
            ->andReturn([]);

        $service = new CloudflareAnalyticsService($client, $this->mockSettings(), $graphql);
        $result = $service->getGraphQLCaptchaSolveRate('rule-abc', 30);

        $this->assertEmpty($result);
    }

    public function test_getGraphQLCaptchaSolveRate_uses_custom_zone_id(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $graphql = Mockery::mock(CloudflareGraphQLService::class);
        $graphql->shouldReceive('getCaptchaSolveRate')
            ->once()
            ->with('other-zone', 'rule-abc', 1)
            ->andReturn([]);

        $service = new CloudflareAnalyticsService($client, $this->mockSettings(), $graphql);
        $result = $service->getGraphQLCaptchaSolveRate('rule-abc', zoneId: 'other-zone');

        $this->assertEmpty($result);
    }

    // --- getGraphQLRuleActivity ---

    public function test_getGraphQLRuleActivity_delegates_correctly(): void
    {
        $activityData = [
            'viewer' => [
                'zones' => [
                    ['issued' => [['count' => 200]]],
                ],
            ],
        ];

        $client = Mockery::mock(CloudflareClientInterface::class);
        $graphql = Mockery::mock(CloudflareGraphQLService::class);
        $graphql->shouldReceive('getRuleActivity')
            ->once()
            ->with('zone-123', 'rule-xyz', 1)
            ->andReturn($activityData);

        $service = new CloudflareAnalyticsService($client, $this->mockSettings(), $graphql);
        $result = $service->getGraphQLRuleActivity('rule-xyz');

        $this->assertEquals($activityData, $result);
    }

    public function test_getGraphQLRuleActivity_passes_custom_days_and_zone(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $graphql = Mockery::mock(CloudflareGraphQLService::class);
        $graphql->shouldReceive('getRuleActivity')
            ->once()
            ->with('my-zone', 'rule-xyz', 14)
            ->andReturn([]);

        $service = new CloudflareAnalyticsService($client, $this->mockSettings(), $graphql);
        $result = $service->getGraphQLRuleActivity('rule-xyz', 14, 'my-zone');

        $this->assertEmpty($result);
    }

    public function test_getGraphQLRuleActivity_throws_when_no_zone_id(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $graphql = Mockery::mock(CloudflareGraphQLService::class);
        $service = new CloudflareAnalyticsService($client, $this->mockSettings(''), $graphql);

        $this->expectException(CloudflareConfigurationException::class);
        $service->getGraphQLRuleActivity('rule-xyz');
    }
}
