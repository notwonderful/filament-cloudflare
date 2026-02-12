<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Http;

use notwonderful\FilamentCloudflare\Exceptions\CloudflareApiException;
use Psr\Http\Message\ResponseInterface;

readonly class CloudflareResponse
{
    /** @var array<string, mixed> */
    public array $data;
    public bool $success;
    /** @var array<int, array<string, mixed>> */
    public array $errors;
    /** @var array<int, mixed> */
    public array $messages;
    /** @var array<string, mixed> */
    public array $resultInfo;
    public mixed $result;

    public function __construct(ResponseInterface $response)
    {
        $body = json_decode($response->getBody()->getContents(), true);

        if (! is_array($body)) {
            throw new \InvalidArgumentException('Invalid JSON response from Cloudflare API');
        }

        $this->data = $body;
        $this->success = $body['success'] ?? false;
        $this->errors = $body['errors'] ?? [];
        $this->messages = $body['messages'] ?? [];
        $this->resultInfo = $body['result_info'] ?? [];
        $this->result = $body['result'] ?? null;
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /** @return array<int, array<string, mixed>> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?string
    {
        if (empty($this->errors)) {
            return null;
        }

        $firstError = $this->errors[0];
        return $firstError['message'] ?? (string) ($firstError['code'] ?? 'Unknown error');
    }

    /** @return array<int, mixed> */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /** @return array<string, mixed> */
    public function getResultInfo(): array
    {
        return $this->resultInfo;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Throw exception if response is not successful
     * @throws CloudflareApiException
     */
    public function throwIfFailed(): void
    {
        if ($this->isSuccessful()) {
            return;
        }

        throw CloudflareApiException::fromResponse($this->toArray());
    }
}
