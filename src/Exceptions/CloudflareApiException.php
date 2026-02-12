<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Exceptions;

class CloudflareApiException extends CloudflareException
{
    /** @var array<int, array<string, mixed>> */
    protected array $errors;

    /** @param array<int, array<string, mixed>> $errors */
    public function __construct(string $message = '', array $errors = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /** @return array<int, array<string, mixed>> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if the API response contains a specific Cloudflare error code.
     */
    public function hasErrorCode(int $code): bool
    {
        foreach ($this->errors as $error) {
            if (($error['code'] ?? null) === $code) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $response */
    public static function fromResponse(array $response): self
    {
        $errors = $response['errors'] ?? [];
        $firstError = $errors[0] ?? [];
        $message = $firstError['message'] ?? ($firstError['code'] ?? 'Unknown Cloudflare API error');
        $code = (int) ($firstError['code'] ?? 0);

        return new self($message, $errors, $code);
    }
}
