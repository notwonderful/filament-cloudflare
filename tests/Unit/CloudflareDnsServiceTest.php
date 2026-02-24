<?php

declare(strict_types=1);

namespace Tests\Unit;

use Mockery;
use notwonderful\FilamentCloudflare\Contracts\CloudflareClientInterface;
use notwonderful\FilamentCloudflare\Contracts\CloudflareSettingsInterface;
use notwonderful\FilamentCloudflare\Enums\DnsRecordType;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareApiException;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareConfigurationException;
use notwonderful\FilamentCloudflare\Http\CloudflareResponse;
use notwonderful\FilamentCloudflare\Services\Dns\CloudflareDnsService;
use Orchestra\Testbench\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class CloudflareDnsServiceTest extends TestCase
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

    // --- listRecords ---

    public function test_listRecords_returns_records_and_result_info(): void
    {
        $records = [
            ['id' => 'rec-1', 'type' => 'A', 'name' => 'example.com', 'content' => '1.2.3.4'],
            ['id' => 'rec-2', 'type' => 'CNAME', 'name' => 'www.example.com', 'content' => 'example.com'],
        ];
        $resultInfo = ['page' => 1, 'per_page' => 100, 'total_count' => 2, 'count' => 2];

        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'zones/zone-123/dns_records', Mockery::on(
                fn (array $opts) => $opts['query']['page'] === 1
                    && $opts['query']['per_page'] === 100
                    && $opts['query']['order'] === 'type'
                    && $opts['query']['direction'] === 'asc'
            ))
            ->andReturn($this->makeResponse([
                'success' => true,
                'result' => $records,
                'result_info' => $resultInfo,
            ]));

        $service = new CloudflareDnsService($client, $this->mockSettings());
        $result = $service->listRecords();

        $this->assertEquals($records, $result['records']);
        $this->assertEquals($resultInfo, $result['result_info']);
    }

    public function test_listRecords_passes_type_filter(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'zones/zone-123/dns_records', Mockery::on(
                fn (array $opts) => $opts['query']['type'] === 'A'
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => [], 'result_info' => []]));

        $service = new CloudflareDnsService($client, $this->mockSettings());
        $result = $service->listRecords(['type' => 'A']);

        $this->assertEmpty($result['records']);
    }

    public function test_listRecords_passes_name_and_content_filters(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'zones/zone-123/dns_records', Mockery::on(
                fn (array $opts) => $opts['query']['name'] === 'sub.example.com'
                    && $opts['query']['content'] === '1.2.3.4'
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => [], 'result_info' => []]));

        $service = new CloudflareDnsService($client, $this->mockSettings());
        $result = $service->listRecords(['name' => 'sub.example.com', 'content' => '1.2.3.4']);

        $this->assertEmpty($result['records']);
    }

    public function test_listRecords_throws_when_no_zone_id(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $service = new CloudflareDnsService($client, $this->mockSettings(''));

        $this->expectException(CloudflareConfigurationException::class);
        $service->listRecords();
    }

    // --- getRecord ---

    public function test_getRecord_returns_single_record(): void
    {
        $record = ['id' => 'rec-1', 'type' => 'A', 'name' => 'example.com', 'content' => '1.2.3.4', 'ttl' => 1];

        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('GET', 'zones/zone-123/dns_records/rec-1')
            ->andReturn($this->makeResponse(['success' => true, 'result' => $record]));

        $service = new CloudflareDnsService($client, $this->mockSettings());
        $this->assertEquals($record, $service->getRecord('rec-1'));
    }

    // --- createRecord ---

    public function test_createRecord_sends_correct_payload_for_A_record(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('POST', 'zones/zone-123/dns_records', Mockery::on(
                fn (array $opts) => $opts['json']['type'] === 'A'
                    && $opts['json']['name'] === 'blog'
                    && $opts['json']['content'] === '1.2.3.4'
                    && $opts['json']['ttl'] === 3600
                    && $opts['json']['proxied'] === true
                    && $opts['json']['comment'] === 'Blog server'
                    && ! isset($opts['json']['priority'])
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'rec-new']]));

        $service = new CloudflareDnsService($client, $this->mockSettings());
        $result = $service->createRecord(
            type: DnsRecordType::A,
            name: 'blog',
            content: '1.2.3.4',
            ttl: 3600,
            proxied: true,
            comment: 'Blog server',
        );

        $this->assertEquals(['id' => 'rec-new'], $result);
    }

    public function test_createRecord_includes_priority_for_MX(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('POST', 'zones/zone-123/dns_records', Mockery::on(
                fn (array $opts) => $opts['json']['type'] === 'MX'
                    && $opts['json']['priority'] === 10
                    && ! isset($opts['json']['proxied'])
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'rec-mx']]));

        $service = new CloudflareDnsService($client, $this->mockSettings());
        $result = $service->createRecord(
            type: DnsRecordType::MX,
            name: '@',
            content: 'mail.example.com',
            priority: 10,
        );

        $this->assertEquals(['id' => 'rec-mx'], $result);
    }

    public function test_createRecord_excludes_proxied_for_TXT(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('POST', 'zones/zone-123/dns_records', Mockery::on(
                fn (array $opts) => $opts['json']['type'] === 'TXT'
                    && ! isset($opts['json']['proxied'])
                    && ! isset($opts['json']['priority'])
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'rec-txt']]));

        $service = new CloudflareDnsService($client, $this->mockSettings());
        $result = $service->createRecord(
            type: DnsRecordType::TXT,
            name: '@',
            content: 'v=spf1 include:_spf.google.com ~all',
        );

        $this->assertEquals(['id' => 'rec-txt'], $result);
    }

    public function test_createRecord_with_custom_zone_id(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('POST', 'zones/custom-zone/dns_records', Mockery::any())
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'rec-1']]));

        $service = new CloudflareDnsService($client, $this->mockSettings());
        $result = $service->createRecord(
            type: DnsRecordType::A,
            name: 'test',
            content: '1.2.3.4',
            zoneId: 'custom-zone',
        );

        $this->assertEquals(['id' => 'rec-1'], $result);
    }

    // --- updateRecord ---

    public function test_updateRecord_sends_patch_request(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('PATCH', 'zones/zone-123/dns_records/rec-1', Mockery::on(
                fn (array $opts) => $opts['json']['type'] === 'A'
                    && $opts['json']['name'] === 'blog'
                    && $opts['json']['content'] === '5.6.7.8'
                    && $opts['json']['ttl'] === 300
                    && $opts['json']['proxied'] === false
            ))
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'rec-1']]));

        $service = new CloudflareDnsService($client, $this->mockSettings());
        $result = $service->updateRecord(
            recordId: 'rec-1',
            type: DnsRecordType::A,
            name: 'blog',
            content: '5.6.7.8',
            ttl: 300,
            proxied: false,
        );

        $this->assertEquals(['id' => 'rec-1'], $result);
    }

    // --- deleteRecord ---

    public function test_deleteRecord_sends_delete_request(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('DELETE', 'zones/zone-123/dns_records/rec-1')
            ->andReturn($this->makeResponse(['success' => true, 'result' => ['id' => 'rec-1']]));

        $service = new CloudflareDnsService($client, $this->mockSettings());
        $result = $service->deleteRecord('rec-1');

        $this->assertEquals(['id' => 'rec-1'], $result);
    }

    // --- exportRecords ---

    public function test_exportRecords_returns_bind_content(): void
    {
        $bindContent = "; Zone file\nexample.com. 300 IN A 1.2.3.4\n";

        $body = Mockery::mock(StreamInterface::class);
        $body->shouldReceive('__toString')->andReturn($bindContent);

        $rawResponse = Mockery::mock(ResponseInterface::class);
        $rawResponse->shouldReceive('getBody')->andReturn($body);

        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('request')
            ->once()
            ->with('GET', 'zones/zone-123/dns_records/export')
            ->andReturn($rawResponse);

        $service = new CloudflareDnsService($client, $this->mockSettings());
        $result = $service->exportRecords();

        $this->assertEquals($bindContent, $result);
    }

    // --- importRecords ---

    public function test_importRecords_sends_multipart_request(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->with('POST', 'zones/zone-123/dns_records/import', Mockery::on(
                fn (array $opts) => isset($opts['multipart'])
                    && count($opts['multipart']) === 2
                    && $opts['multipart'][0]['name'] === 'file'
                    && $opts['multipart'][0]['contents'] === 'example.com. 300 IN A 1.2.3.4'
                    && $opts['multipart'][1]['name'] === 'proxied'
                    && $opts['multipart'][1]['contents'] === 'true'
            ))
            ->andReturn($this->makeResponse([
                'success' => true,
                'result' => ['recs_added' => 1, 'total_records_parsed' => 1],
            ]));

        $service = new CloudflareDnsService($client, $this->mockSettings());
        $result = $service->importRecords('example.com. 300 IN A 1.2.3.4', proxied: true);

        $this->assertEquals(1, $result['recs_added']);
        $this->assertEquals(1, $result['total_records_parsed']);
    }

    // --- API errors ---

    public function test_listRecords_throws_on_api_error(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->andReturn($this->makeResponse([
                'success' => false,
                'errors' => [['code' => 7003, 'message' => 'Could not route to zone']],
            ]));

        $this->expectException(CloudflareApiException::class);

        $service = new CloudflareDnsService($client, $this->mockSettings());
        $service->listRecords();
    }

    public function test_createRecord_throws_on_api_error(): void
    {
        $client = Mockery::mock(CloudflareClientInterface::class);
        $client->shouldReceive('makeRequest')
            ->once()
            ->andReturn($this->makeResponse([
                'success' => false,
                'errors' => [['code' => 81057, 'message' => 'The record already exists']],
            ]));

        $this->expectException(CloudflareApiException::class);

        $service = new CloudflareDnsService($client, $this->mockSettings());
        $service->createRecord(DnsRecordType::A, 'test', '1.2.3.4');
    }
}
