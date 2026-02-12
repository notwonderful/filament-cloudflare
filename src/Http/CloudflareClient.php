<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use notwonderful\FilamentCloudflare\Contracts\CloudflareAuthInterface;
use notwonderful\FilamentCloudflare\Contracts\CloudflareClientInterface;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareRequestException;
use Psr\Http\Message\ResponseInterface;

class CloudflareClient implements CloudflareClientInterface
{
    protected Client $client;
    protected string $baseUrl = 'https://api.cloudflare.com/client/v4';

    private const int MAX_RETRIES = 3;
    private const int RETRY_DELAY_MS = 1000;

    public function __construct(
        protected readonly CloudflareAuthInterface $auth,
        ?string $baseUrl = null,
        ?HandlerStack $handler = null,
    ) {
        $this->baseUrl = $baseUrl ?? $this->baseUrl;

        $stack = $handler ?? HandlerStack::create();
        $stack->push(Middleware::retry($this->retryDecider(), $this->retryDelay()));

        $this->client = new Client([
            'base_uri' => $this->baseUrl . '/',
            'timeout' => 30,
            'http_errors' => false,
            'handler' => $stack,
        ]);
    }

    /**
     * Make a raw HTTP request to Cloudflare API.
     *
     * @param array<string, mixed> $options
     * @throws CloudflareRequestException
     */
    public function request(string $method, string $endpoint, array $options = []): ResponseInterface
    {
        if (! $this->auth->hasCredentials()) {
            $this->auth->refreshCredentials();
        }

        $options['headers'] = [
            ...($options['headers'] ?? []),
            ...$this->auth->getAuthHeaders(),
        ];

        try {
            return $this->client->request($method, $endpoint, $options);
        } catch (GuzzleException $e) {
            throw new CloudflareRequestException(
                'Cloudflare HTTP request failed: ' . $e->getMessage(),
                $method,
                $endpoint,
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * @param array<string, mixed> $options
     * @throws CloudflareRequestException
     */
    public function makeRequest(string $method, string $endpoint, array $options = []): CloudflareResponse
    {
        $response = $this->request($method, $endpoint, $options);

        return new CloudflareResponse($response);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Create a new client instance for GraphQL endpoint.
     */
    public function forGraphQL(): Client
    {
        return new Client([
            'base_uri' => 'https://api.cloudflare.com/client/v4/graphql',
            'timeout' => 30,
            'headers' => [
                ...$this->auth->getAuthHeaders(),
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Decide whether to retry a request.
     *
     * Retries on 429 (rate limit) and 5xx (server errors).
     */
    private function retryDecider(): \Closure
    {
        return function (
            int $retries,
            Request $request,
            ?Response $response = null,
            ?\Throwable $exception = null,
        ): bool {
            if ($retries >= self::MAX_RETRIES) {
                return false;
            }

            if ($response !== null) {
                $status = $response->getStatusCode();

                // Retry on rate limit or server errors
                return $status === 429 || $status >= 500;
            }

            // Retry on connection errors
            return $exception !== null;
        };
    }

    /**
     * Calculate retry delay with exponential backoff.
     *
     * On 429 responses, respects the Retry-After header if present.
     */
    private function retryDelay(): \Closure
    {
        return function (int $retries, ?Response $response = null): int {
            // Respect Retry-After header from Cloudflare
            if ($response !== null && $response->getStatusCode() === 429) {
                $retryAfter = $response->getHeaderLine('Retry-After');
                if (is_numeric($retryAfter)) {
                    return (int) ($retryAfter * 1000);
                }
            }

            // Exponential backoff: 1s, 2s, 4s
            return self::RETRY_DELAY_MS * (int) pow(2, $retries);
        };
    }
}
