<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Contracts;

interface CloudflareAuthInterface
{
    public function refreshCredentials(): void;

    public function setCredentials(?string $email = null, ?string $apiKey = null, ?string $token = null): void;

    /** @return array<string, string> */
    public function getAuthHeaders(): array;

    public function hasCredentials(): bool;

    public function getToken(): ?string;

    public function getEmail(): ?string;

    public function getApiKey(): ?string;
}
