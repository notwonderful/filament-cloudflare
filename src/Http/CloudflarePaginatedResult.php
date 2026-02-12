<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Http;

/**
 * DTO for Cloudflare API responses that include pagination metadata.
 *
 * All paginated service methods return this instead of a raw array,
 * giving callers a consistent, typed contract for both data and
 * pagination info.
 */
readonly class CloudflarePaginatedResult
{
    /**
     * @param array<int, array<string, mixed>> $items     The result items
     * @param array<string, mixed>             $resultInfo  Pagination metadata (page, per_page, total_count, total_pages)
     */
    public function __construct(
        public array $items,
        public array $resultInfo = [],
    ) {}

    public function totalPages(): int
    {
        return (int) ($this->resultInfo['total_pages'] ?? 1);
    }

    public function totalCount(): int
    {
        return (int) ($this->resultInfo['total_count'] ?? count($this->items));
    }

    public function currentPage(): int
    {
        return (int) ($this->resultInfo['page'] ?? 1);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
