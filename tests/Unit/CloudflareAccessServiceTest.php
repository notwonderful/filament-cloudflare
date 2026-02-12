<?php

declare(strict_types=1);

namespace Tests\Unit;

use Mockery;
use notwonderful\FilamentCloudflare\Contracts\CloudflareClientInterface;
use notwonderful\FilamentCloudflare\Contracts\CloudflareSettingsInterface;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareConfigurationException;
use notwonderful\FilamentCloudflare\Http\CloudflareResponse;
use notwonderful\FilamentCloudflare\Services\Access\CloudflareAccessService;
use Orchestra\Testbench\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class CloudflareAccessServiceTest extends TestCase
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

    private function mockSettings(string $zoneId = 'zone-123', string $accountId = 'acc-456'): CloudflareSettingsInterface
    {
        $mock = Mockery::mock(CloudflareSettingsInterface::class);
        $mock->shouldReceive('get')->with('cloudflare_zone_id')->andReturn($zoneId);
        $mock->shouldReceive('get')->with('cloudflare_account_id')->andReturn($accountId);

        return $mock;
    }

    // --- getAccessApps ---

    public function test_getAccessApps_returns_apps(): void
    {
        $apps = [
            ['id' => 'app-1', 'name' => 'Admin Protection'],
            ['id' => 'app-2', 'name' => 'Install Protection'],
        ];

        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'accounts/acc-456/access/apps')
            ->andReturn($this->makeResponse(['success' => true, 'result' => $apps]));

        $service = new CloudflareAccessService($client, $this->mockSettings());
        $this->assertEquals($apps, $service->getAccessApps());
    }

    public function test_getAccessApps_returns_empty_when_result_is_null(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'accounts/acc-456/access/apps')
            ->andReturn($this->makeResponse(['success' => true, 'result' => null]));

        $service = new CloudflareAccessService($client, $this->mockSettings());
        $this->assertEmpty($service->getAccessApps());
    }

    public function test_getAccessApps_throws_when_no_account_id(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $service = new CloudflareAccessService($client, $this->mockSettings(accountId: ''));

        $this->expectException(CloudflareConfigurationException::class);
        $this->expectExceptionMessage('Account ID');
        $service->getAccessApps();
    }

    // --- getAccessGroups ---

    public function test_getAccessGroups_returns_groups(): void
    {
        $groups = [
            ['id' => 'grp-1', 'name' => 'Admins'],
        ];

        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'accounts/acc-456/access/groups')
            ->andReturn($this->makeResponse(['success' => true, 'result' => $groups]));

        $service = new CloudflareAccessService($client, $this->mockSettings());
        $this->assertEquals($groups, $service->getAccessGroups());
    }

    public function test_getAccessGroups_throws_when_no_account_id(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $service = new CloudflareAccessService($client, $this->mockSettings(accountId: ''));

        $this->expectException(CloudflareConfigurationException::class);
        $service->getAccessGroups();
    }

    // --- getAccessIdentityProviders ---

    public function test_getAccessIdentityProviders_returns_providers(): void
    {
        $providers = [
            ['id' => 'idp-1', 'name' => 'Google', 'type' => 'google'],
        ];

        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'accounts/acc-456/access/identity_providers')
            ->andReturn($this->makeResponse(['success' => true, 'result' => $providers]));

        $service = new CloudflareAccessService($client, $this->mockSettings());
        $this->assertEquals($providers, $service->getAccessIdentityProviders());
    }

    // --- createAdminAccessApp ---

    public function test_createAdminAccessApp_creates_admin_app(): void
    {
        $zoneResult = ['name' => 'example.com'];
        $appResult = ['id' => 'app-new', 'name' => 'Laravel admin protection'];

        $client = Mockery::mock(CloudflareClientInterface::class);

        // getAccessIdentityProviders call
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'accounts/acc-456/access/identity_providers')
            ->andReturn($this->makeResponse(['success' => true, 'result' => [['id' => 'idp-1']]]));

        // zone details call
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'zones/zone-123')
            ->andReturn($this->makeResponse(['success' => true, 'result' => $zoneResult]));

        // create app call
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('POST', 'accounts/acc-456/access/apps', Mockery::on(
                fn (array $opts) => $opts['json']['name'] === 'Laravel admin protection'
                    && $opts['json']['type'] === 'self_hosted'
                    && $opts['json']['destinations'][0]['uri'] === 'example.com/admin'
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => $appResult]));

        $service = new CloudflareAccessService($client, $this->mockSettings());
        $result = $service->createAdminAccessApp('admin');

        $this->assertEquals($appResult, $result);
    }

    public function test_createAdminAccessApp_creates_install_app(): void
    {
        $zoneResult = ['name' => 'example.com'];
        $appResult = ['id' => 'app-inst', 'name' => 'Laravel install protection'];

        $client = Mockery::mock(CloudflareClientInterface::class);

        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'accounts/acc-456/access/identity_providers')
            ->andReturn($this->makeResponse(['success' => true, 'result' => [['id' => 'idp-1']]]));

        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'zones/zone-123')
            ->andReturn($this->makeResponse(['success' => true, 'result' => $zoneResult]));

        $client->shouldReceive('makeRequest')
            ->once()
            ->with('POST', 'accounts/acc-456/access/apps', Mockery::on(
                fn (array $opts) => $opts['json']['name'] === 'Laravel install protection'
                    && $opts['json']['destinations'][0]['uri'] === 'example.com/install'
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => $appResult]));

        $service = new CloudflareAccessService($client, $this->mockSettings());
        $result = $service->createAdminAccessApp('install');

        $this->assertEquals($appResult, $result);
    }

    public function test_createAdminAccessApp_throws_when_no_identity_providers(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);

        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'accounts/acc-456/access/identity_providers')
            ->andReturn($this->makeResponse(['success' => true, 'result' => []]));

        $service = new CloudflareAccessService($client, $this->mockSettings());

        $this->expectException(CloudflareConfigurationException::class);
        $this->expectExceptionMessage('No Cloudflare Access login methods configured');
        $service->createAdminAccessApp();
    }

    public function test_createAdminAccessApp_uses_custom_zone_id(): void
    {
        $zoneResult = ['name' => 'custom.com'];
        $appResult = ['id' => 'app-custom'];

        $client = Mockery::mock(CloudflareClientInterface::class);

        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'accounts/acc-456/access/identity_providers')
            ->andReturn($this->makeResponse(['success' => true, 'result' => [['id' => 'idp-1']]]));

        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'zones/custom-zone')
            ->andReturn($this->makeResponse(['success' => true, 'result' => $zoneResult]));

        $client->shouldReceive('makeRequest')
            ->once()
            ->with('POST', 'accounts/acc-456/access/apps', Mockery::on(
                fn (array $opts) => $opts['json']['destinations'][0]['uri'] === 'custom.com/admin'
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => $appResult]));

        $service = new CloudflareAccessService($client, $this->mockSettings());
        $result = $service->createAdminAccessApp('admin', 'custom-zone');

        $this->assertEquals($appResult, $result);
    }

    // --- deleteAccessApp ---

    public function test_deleteAccessApp_sends_delete_request(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('DELETE', 'accounts/acc-456/access/apps/app-99')
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'app-99']]));

        $service = new CloudflareAccessService($client, $this->mockSettings());
        $result = $service->deleteAccessApp('app-99');

        $this->assertEquals(['id' => 'app-99'], $result);
    }

    public function test_deleteAccessApp_throws_when_no_account_id(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $service = new CloudflareAccessService($client, $this->mockSettings(accountId: ''));

        $this->expectException(CloudflareConfigurationException::class);
        $service->deleteAccessApp('app-1');
    }

    public function test_createAdminAccessApp_defaults_to_admin_for_unknown_type(): void
    {
        $zoneResult = ['name' => 'example.com'];
        $appResult = ['id' => 'app-default'];

        $client = Mockery::mock(CloudflareClientInterface::class);

        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'accounts/acc-456/access/identity_providers')
            ->andReturn($this->makeResponse(['success' => true, 'result' => [['id' => 'idp-1']]]));

        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'zones/zone-123')
            ->andReturn($this->makeResponse(['success' => true, 'result' => $zoneResult]));

        $client->shouldReceive('makeRequest')
            ->once()
            ->with('POST', 'accounts/acc-456/access/apps', Mockery::on(
                fn (array $opts) => $opts['json']['destinations'][0]['uri'] === 'example.com/admin'
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => $appResult]));

        $service = new CloudflareAccessService($client, $this->mockSettings());
        $result = $service->createAdminAccessApp('unknown');

        $this->assertEquals($appResult, $result);
    }
}
