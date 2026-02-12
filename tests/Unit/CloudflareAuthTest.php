<?php

declare(strict_types=1);

namespace Tests\Unit;

use Mockery;
use notwonderful\FilamentCloudflare\Auth\CloudflareAuth;
use notwonderful\FilamentCloudflare\Contracts\CloudflareSettingsInterface;
use Orchestra\Testbench\TestCase;

class CloudflareAuthTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockSettings(?string $token = null, ?string $email = null, ?string $apiKey = null): CloudflareSettingsInterface
    {
        $mock = Mockery::mock(CloudflareSettingsInterface::class);
        $mock->shouldReceive('get')->with('cloudflare_token')->andReturn($token);
        $mock->shouldReceive('get')->with('cloudflare_email')->andReturn($email);
        $mock->shouldReceive('get')->with('cloudflare_api_key')->andReturn($apiKey);

        return $mock;
    }

    public function test_getAuthHeaders_returns_bearer_token_when_token_exists(): void
    {
        $auth = new CloudflareAuth($this->mockSettings(token: 'test-token-123'));
        $headers = $auth->getAuthHeaders();

        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals('Bearer test-token-123', $headers['Authorization']);
        $this->assertArrayNotHasKey('X-Auth-Email', $headers);
        $this->assertArrayNotHasKey('X-Auth-Key', $headers);
    }

    public function test_getAuthHeaders_returns_email_and_key_when_token_not_exists(): void
    {
        $auth = new CloudflareAuth($this->mockSettings(email: 'test@example.com', apiKey: 'test-api-key-123'));
        $headers = $auth->getAuthHeaders();

        $this->assertArrayHasKey('X-Auth-Email', $headers);
        $this->assertEquals('test@example.com', $headers['X-Auth-Email']);
        $this->assertArrayHasKey('X-Auth-Key', $headers);
        $this->assertEquals('test-api-key-123', $headers['X-Auth-Key']);
        $this->assertArrayNotHasKey('Authorization', $headers);
    }

    public function test_getAuthHeaders_returns_only_content_type_when_no_credentials(): void
    {
        $auth = new CloudflareAuth($this->mockSettings());
        $headers = $auth->getAuthHeaders();

        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertEquals('application/json', $headers['Content-Type']);
        $this->assertArrayNotHasKey('Authorization', $headers);
        $this->assertArrayNotHasKey('X-Auth-Email', $headers);
        $this->assertArrayNotHasKey('X-Auth-Key', $headers);
        $this->assertCount(1, $headers);
    }

    public function test_getAuthHeaders_prioritizes_token_over_email_and_key(): void
    {
        $auth = new CloudflareAuth($this->mockSettings(
            token: 'token-has-priority',
            email: 'email@example.com',
            apiKey: 'api-key-123',
        ));
        $headers = $auth->getAuthHeaders();

        $this->assertEquals('Bearer token-has-priority', $headers['Authorization']);
        $this->assertArrayNotHasKey('X-Auth-Email', $headers);
        $this->assertArrayNotHasKey('X-Auth-Key', $headers);
    }

    public function test_hasCredentials_returns_true_when_token_exists(): void
    {
        $auth = new CloudflareAuth($this->mockSettings(token: 'test-token'));
        $this->assertTrue($auth->hasCredentials());
    }

    public function test_hasCredentials_returns_true_when_email_and_key_exist(): void
    {
        $auth = new CloudflareAuth($this->mockSettings(email: 'test@example.com', apiKey: 'test-key'));
        $this->assertTrue($auth->hasCredentials());
    }

    public function test_hasCredentials_returns_false_when_no_credentials(): void
    {
        $auth = new CloudflareAuth($this->mockSettings());
        $this->assertFalse($auth->hasCredentials());
    }

    public function test_hasCredentials_returns_false_when_only_email_without_key(): void
    {
        $auth = new CloudflareAuth($this->mockSettings(email: 'test@example.com'));
        $this->assertFalse($auth->hasCredentials());
    }

    public function test_setCredentials_overrides_settings_credentials(): void
    {
        $auth = new CloudflareAuth($this->mockSettings(token: 'token-from-settings'));

        $headersBefore = $auth->getAuthHeaders();
        $this->assertEquals('Bearer token-from-settings', $headersBefore['Authorization']);

        $auth->setCredentials('manual@example.com', 'manual-key', 'manual-token');

        $headersAfter = $auth->getAuthHeaders();
        $this->assertEquals('Bearer manual-token', $headersAfter['Authorization']);
        $this->assertEquals('manual@example.com', $auth->getEmail());
        $this->assertEquals('manual-key', $auth->getApiKey());
        $this->assertEquals('manual-token', $auth->getToken());
    }

    public function test_refreshCredentials_reloads_credentials_from_settings(): void
    {
        $mockSettings = Mockery::mock(CloudflareSettingsInterface::class);

        // First call (constructor)
        $mockSettings->shouldReceive('get')->with('cloudflare_token')->andReturn('old-token')->once();
        $mockSettings->shouldReceive('get')->with('cloudflare_email')->andReturn(null)->once();
        $mockSettings->shouldReceive('get')->with('cloudflare_api_key')->andReturn(null)->once();

        // Second call (after refreshCredentials)
        $mockSettings->shouldReceive('get')->with('cloudflare_token')->andReturn('new-token')->once();
        $mockSettings->shouldReceive('get')->with('cloudflare_email')->andReturn('new@example.com')->once();
        $mockSettings->shouldReceive('get')->with('cloudflare_api_key')->andReturn('new-key')->once();

        $auth = new CloudflareAuth($mockSettings);
        $this->assertEquals('Bearer old-token', $auth->getAuthHeaders()['Authorization']);

        $auth->refreshCredentials();

        $headers = $auth->getAuthHeaders();
        $this->assertEquals('Bearer new-token', $headers['Authorization']);
    }
}
