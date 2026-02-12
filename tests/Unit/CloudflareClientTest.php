<?php

declare(strict_types=1);

namespace Tests\Unit;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Mockery;
use notwonderful\FilamentCloudflare\Contracts\CloudflareAuthInterface;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareRequestException;
use notwonderful\FilamentCloudflare\Http\CloudflareClient;
use Orchestra\Testbench\TestCase;

class CloudflareClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockAuth(bool $hasCredentials = true): CloudflareAuthInterface
    {
        $auth = Mockery::mock(CloudflareAuthInterface::class);
        $auth->shouldReceive('hasCredentials')->andReturn($hasCredentials);
        $auth->shouldReceive('getAuthHeaders')->andReturn([
            'Authorization' => 'Bearer test-token',
            'Content-Type' => 'application/json',
        ]);

        if (! $hasCredentials) {
            $auth->shouldReceive('refreshCredentials');
        }

        return $auth;
    }

    // --- makeRequest ---

    public function test_makeRequest_returns_cloudflare_response(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'success' => true,
                'result' => ['id' => 'test-123'],
            ])),
        ]);

        $client = new CloudflareClient($this->mockAuth(), handler: HandlerStack::create($mockHandler));
        $response = $client->makeRequest('GET', 'zones');

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals(['id' => 'test-123'], $response->getResult());
    }

    public function test_makeRequest_sends_auth_headers(): void
    {
        $sentHeaders = [];

        $mockHandler = new MockHandler([
            function ($request) use (&$sentHeaders) {
                $sentHeaders = $request->getHeaders();
                return new Response(200, [], json_encode(['success' => true, 'result' => []]));
            },
        ]);

        $client = new CloudflareClient($this->mockAuth(), handler: HandlerStack::create($mockHandler));
        $client->makeRequest('GET', 'zones');

        $this->assertArrayHasKey('Authorization', $sentHeaders);
        $this->assertEquals(['Bearer test-token'], $sentHeaders['Authorization']);
    }

    public function test_refreshes_credentials_when_not_available(): void
    {
        $auth = Mockery::mock(CloudflareAuthInterface::class);
        $auth->shouldReceive('hasCredentials')->once()->andReturn(false);
        $auth->shouldReceive('refreshCredentials')->once();
        $auth->shouldReceive('getAuthHeaders')->andReturn([
            'Authorization' => 'Bearer refreshed-token',
            'Content-Type' => 'application/json',
        ]);

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['success' => true, 'result' => []])),
        ]);

        $client = new CloudflareClient($auth, handler: HandlerStack::create($mockHandler));
        $response = $client->makeRequest('GET', 'zones');

        $this->assertTrue($response->isSuccessful());
    }

    // --- retry logic ---

    public function test_retries_on_429_rate_limit(): void
    {
        $callCount = 0;

        $mockHandler = new MockHandler([
            new Response(429, ['Retry-After' => '0'], json_encode([
                'success' => false,
                'errors' => [['message' => 'Rate limited']],
            ])),
            new Response(200, [], json_encode([
                'success' => true,
                'result' => ['id' => 'after-retry'],
            ])),
        ]);

        $client = new CloudflareClient($this->mockAuth(), handler: HandlerStack::create($mockHandler));
        $response = $client->makeRequest('GET', 'zones');

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals(['id' => 'after-retry'], $response->getResult());
    }

    public function test_retries_on_500_server_error(): void
    {
        $mockHandler = new MockHandler([
            new Response(500, [], json_encode([
                'success' => false,
                'errors' => [['message' => 'Internal server error']],
            ])),
            new Response(200, [], json_encode([
                'success' => true,
                'result' => ['id' => 'recovered'],
            ])),
        ]);

        $client = new CloudflareClient($this->mockAuth(), handler: HandlerStack::create($mockHandler));
        $response = $client->makeRequest('GET', 'zones');

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals(['id' => 'recovered'], $response->getResult());
    }

    public function test_does_not_retry_on_400_client_error(): void
    {
        $mockHandler = new MockHandler([
            new Response(400, [], json_encode([
                'success' => false,
                'errors' => [['code' => 6003, 'message' => 'Bad request']],
            ])),
        ]);

        $client = new CloudflareClient($this->mockAuth(), handler: HandlerStack::create($mockHandler));

        // 400 is not retried — response is returned as-is (not retried like 429/5xx)
        $response = $client->makeRequest('GET', 'zones');

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->hasErrors());
        $this->assertEquals('Bad request', $response->getFirstError());
    }

    // --- error handling ---

    public function test_wraps_guzzle_exceptions(): void
    {
        $mockHandler = new MockHandler([
            new \GuzzleHttp\Exception\ConnectException(
                'Connection refused',
                new \GuzzleHttp\Psr7\Request('GET', 'zones'),
            ),
            new \GuzzleHttp\Exception\ConnectException(
                'Connection refused',
                new \GuzzleHttp\Psr7\Request('GET', 'zones'),
            ),
            new \GuzzleHttp\Exception\ConnectException(
                'Connection refused',
                new \GuzzleHttp\Psr7\Request('GET', 'zones'),
            ),
            new \GuzzleHttp\Exception\ConnectException(
                'Connection refused',
                new \GuzzleHttp\Psr7\Request('GET', 'zones'),
            ),
        ]);

        $client = new CloudflareClient($this->mockAuth(), handler: HandlerStack::create($mockHandler));

        $this->expectException(CloudflareRequestException::class);
        $this->expectExceptionMessageMatches('/Connection refused/');

        $client->makeRequest('GET', 'zones');
    }

    // --- forGraphQL ---

    public function test_forGraphQL_returns_guzzle_client(): void
    {
        $auth = Mockery::mock(CloudflareAuthInterface::class);
        $auth->shouldReceive('getAuthHeaders')->andReturn([
            'Authorization' => 'Bearer token',
            'Content-Type' => 'application/json',
        ]);

        $mockHandler = new MockHandler([]);
        $client = new CloudflareClient($auth, handler: HandlerStack::create($mockHandler));

        $graphqlClient = $client->forGraphQL();
        $this->assertInstanceOf(\GuzzleHttp\Client::class, $graphqlClient);
    }
}
