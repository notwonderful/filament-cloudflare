<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Exceptions;

class CloudflareRequestException extends CloudflareException
{
    protected string $method;
    protected string $endpoint;

    public function __construct(string $message = '', string $method = '', string $endpoint = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->method = $method;
        $this->endpoint = $endpoint;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }
}
