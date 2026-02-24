<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Contracts;

use GuzzleHttp\Client;
use notwonderful\FilamentCloudflare\Http\CloudflareResponse;
use Psr\Http\Message\ResponseInterface;

interface CloudflareClientInterface
{
    /**
     * Make a raw HTTP request
     *
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $endpoint, array $options = []): ResponseInterface;

    /**
     * Make a request and return a parsed CloudflareResponse (JSON).
     *
     * @param array<string, mixed> $options
     */
    public function makeRequest(string $method, string $endpoint, array $options = []): CloudflareResponse;

    public function forGraphQL(): Client;
}
