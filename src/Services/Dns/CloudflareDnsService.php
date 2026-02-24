<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Services\Dns;

use notwonderful\FilamentCloudflare\Enums\DnsRecordType;
use notwonderful\FilamentCloudflare\Services\Base\CloudflareBaseService;

class CloudflareDnsService extends CloudflareBaseService
{
    /**
     * List DNS records with optional filters.
     *
     * @param array<string, mixed> $filters  Optional: type, name, content, page, per_page, order, direction
     * @return array<string, mixed>  Full API response with result + result_info
     */
    public function listRecords(array $filters = [], ?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);

        $query = array_filter([
            'type' => $filters['type'] ?? null,
            'name' => $filters['name'] ?? null,
            'content' => $filters['content'] ?? null,
            'page' => $filters['page'] ?? 1,
            'per_page' => $filters['per_page'] ?? 100,
            'order' => $filters['order'] ?? 'type',
            'direction' => $filters['direction'] ?? 'asc',
        ], fn ($v) => $v !== null);

        $suffix = md5(serialize($query));

        return $this->remember("dns_records:{$zoneId}", function () use ($zoneId, $query) {
            $response = $this->client->makeRequest('GET', "zones/{$zoneId}/dns_records", [
                'query' => $query,
            ]);
            $response->throwIfFailed();

            return [
                'records' => $response->getResult() ?? [],
                'result_info' => $response->getResultInfo(),
            ];
        }, suffix: $suffix);
    }

    /**
     * Get a single DNS record.
     *
     * @return array<string, mixed>
     */
    public function getRecord(string $recordId, ?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);

        return $this->remember("dns_record:{$zoneId}:{$recordId}", function () use ($zoneId, $recordId) {
            $response = $this->client->makeRequest('GET', "zones/{$zoneId}/dns_records/{$recordId}");
            $response->throwIfFailed();

            return $response->getResult() ?? [];
        });
    }

    /**
     * Create a DNS record.
     *
     * @return array<string, mixed>
     */
    public function createRecord(
        DnsRecordType $type,
        string $name,
        string $content,
        int $ttl = 1,
        bool $proxied = false,
        ?int $priority = null,
        ?string $comment = null,
        ?string $zoneId = null,
    ): array {
        $zoneId = $this->ensureZoneId($zoneId);

        $data = array_filter([
            'type' => $type->value,
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl,
            'proxied' => $type->supportsProxy() ? $proxied : null,
            'priority' => $type->requiresPriority() ? $priority : null,
            'comment' => $comment,
        ], fn ($v) => $v !== null);

        $response = $this->client->makeRequest('POST', "zones/{$zoneId}/dns_records", [
            'json' => $data,
        ]);
        $response->throwIfFailed();

        $this->invalidateCache("dns_records:{$zoneId}");

        return $response->getResult() ?? [];
    }

    /**
     * Update an existing DNS record.
     *
     * @return array<string, mixed>
     */
    public function updateRecord(
        string $recordId,
        DnsRecordType $type,
        string $name,
        string $content,
        int $ttl = 1,
        bool $proxied = false,
        ?int $priority = null,
        ?string $comment = null,
        ?string $zoneId = null,
    ): array {
        $zoneId = $this->ensureZoneId($zoneId);

        $data = array_filter([
            'type' => $type->value,
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl,
            'proxied' => $type->supportsProxy() ? $proxied : null,
            'priority' => $type->requiresPriority() ? $priority : null,
            'comment' => $comment,
        ], fn ($v) => $v !== null);

        $response = $this->client->makeRequest('PATCH', "zones/{$zoneId}/dns_records/{$recordId}", [
            'json' => $data,
        ]);
        $response->throwIfFailed();

        $this->invalidateCache("dns_records:{$zoneId}");
        $this->invalidateCache("dns_record:{$zoneId}:{$recordId}");

        return $response->getResult() ?? [];
    }

    /**
     * Delete a DNS record.
     *
     * @return array<string, mixed>
     */
    public function deleteRecord(string $recordId, ?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);

        $response = $this->client->makeRequest('DELETE', "zones/{$zoneId}/dns_records/{$recordId}");
        $response->throwIfFailed();

        $this->invalidateCache("dns_records:{$zoneId}");
        $this->invalidateCache("dns_record:{$zoneId}:{$recordId}");

        return $response->getResult() ?? [];
    }

    /**
     * Export DNS records as BIND zone file.
     */
    public function exportRecords(?string $zoneId = null): string
    {
        $zoneId = $this->ensureZoneId($zoneId);

        $response = $this->client->request('GET', "zones/{$zoneId}/dns_records/export");

        return (string) $response->getBody();
    }

    /**
     * Import DNS records from a BIND zone file.
     *
     * @return array<string, mixed>
     */
    public function importRecords(string $bindContent, bool $proxied = false, ?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);

        $response = $this->client->makeRequest('POST', "zones/{$zoneId}/dns_records/import", [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => $bindContent,
                    'filename' => 'records.txt',
                ],
                [
                    'name' => 'proxied',
                    'contents' => $proxied ? 'true' : 'false',
                ],
            ],
        ]);
        $response->throwIfFailed();

        $this->invalidateCache("dns_records:{$zoneId}");

        return $response->getResult() ?? [];
    }
}
