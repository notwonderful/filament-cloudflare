<?php

declare(strict_types=1);

namespace Tests\Unit;

use Mockery;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareApiException;
use notwonderful\FilamentCloudflare\Http\CloudflareResponse;
use Orchestra\Testbench\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class CloudflareResponseTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeResponse(array $body): CloudflareResponse
    {
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->once()->andReturn(json_encode($body));

        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getBody')->once()->andReturn($stream);

        return new CloudflareResponse($response);
    }

    public function test_parses_successful_response(): void
    {
        $response = $this->makeResponse([
            'success' => true,
            'result' => ['id' => 'abc-123', 'name' => 'test'],
            'errors' => [],
            'messages' => ['Operation succeeded'],
        ]);

        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->hasErrors());
        $this->assertEquals(['id' => 'abc-123', 'name' => 'test'], $response->getResult());
        $this->assertEmpty($response->getErrors());
        $this->assertEquals(['Operation succeeded'], $response->getMessages());
    }

    public function test_parses_failed_response(): void
    {
        $response = $this->makeResponse([
            'success' => false,
            'result' => null,
            'errors' => [
                ['code' => 1004, 'message' => 'Invalid zone ID'],
            ],
        ]);

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->hasErrors());
        $this->assertNull($response->getResult());
        $this->assertEquals('Invalid zone ID', $response->getFirstError());
    }

    public function test_getFirstError_returns_null_when_no_errors(): void
    {
        $response = $this->makeResponse([
            'success' => true,
            'result' => [],
            'errors' => [],
        ]);

        $this->assertNull($response->getFirstError());
    }

    public function test_getFirstError_falls_back_to_code_when_no_message(): void
    {
        $response = $this->makeResponse([
            'success' => false,
            'errors' => [['code' => 9999]],
        ]);

        // getFirstError() returns string|null; code is cast via string interpolation
        $this->assertEquals('9999', $response->getFirstError());
    }

    public function test_getResultInfo_returns_pagination_data(): void
    {
        $response = $this->makeResponse([
            'success' => true,
            'result' => [],
            'result_info' => ['page' => 1, 'per_page' => 20, 'total_count' => 100],
        ]);

        $this->assertEquals(['page' => 1, 'per_page' => 20, 'total_count' => 100], $response->getResultInfo());
    }

    public function test_toArray_returns_full_envelope(): void
    {
        $data = [
            'success' => true,
            'result' => ['id' => 'test'],
            'errors' => [],
            'messages' => [],
        ];

        $response = $this->makeResponse($data);
        $this->assertEquals($data, $response->toArray());
    }

    public function test_throwIfFailed_does_nothing_on_success(): void
    {
        $response = $this->makeResponse([
            'success' => true,
            'result' => ['id' => 'test'],
        ]);

        $response->throwIfFailed();
        $this->assertTrue(true); // No exception thrown
    }

    public function test_throwIfFailed_throws_on_failure(): void
    {
        $response = $this->makeResponse([
            'success' => false,
            'errors' => [['code' => 1004, 'message' => 'DNS record not found']],
        ]);

        $this->expectException(CloudflareApiException::class);
        $this->expectExceptionMessage('DNS record not found');

        $response->throwIfFailed();
    }

    public function test_throws_on_invalid_json(): void
    {
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->once()->andReturn('not valid json{{{');

        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getBody')->once()->andReturn($stream);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON response');

        new CloudflareResponse($response);
    }

    public function test_defaults_missing_fields(): void
    {
        $response = $this->makeResponse([]);

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->hasErrors());
        $this->assertNull($response->getResult());
        $this->assertEmpty($response->getResultInfo());
        $this->assertEmpty($response->getMessages());
    }
}
