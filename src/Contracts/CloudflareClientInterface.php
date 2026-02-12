<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Contracts;

use GuzzleHttp\Client;
use notwonderful\FilamentCloudflare\Http\CloudflareResponse;

interface CloudflareClientInterface
{
    /** @param array<string, mixed> $options */
    public function makeRequest(string $method, string $endpoint, array $options = []): CloudflareResponse;

    public function forGraphQL(): Client;
}
