<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Auth;

use notwonderful\FilamentCloudflare\Contracts\CloudflareAuthInterface;
use notwonderful\FilamentCloudflare\Contracts\CloudflareSettingsInterface;

class CloudflareAuth implements CloudflareAuthInterface
{
    protected ?string $email = null;
    protected ?string $apiKey = null;
    protected ?string $token = null;
    protected bool $credentialsManuallySet = false;

    public function __construct(
        protected readonly CloudflareSettingsInterface $settings
    ) {
        $this->refreshCredentials();
    }

    public function refreshCredentials(): void
    {
        $this->email = $this->settings->get('cloudflare_email');
        $this->apiKey = $this->settings->get('cloudflare_api_key');
        $this->token = $this->settings->get('cloudflare_token');
    }

    public function setCredentials(?string $email = null, ?string $apiKey = null, ?string $token = null): void
    {
        $this->email = $email;
        $this->apiKey = $apiKey;
        $this->token = $token;
        $this->credentialsManuallySet = true;
    }

    /** @return array<string, string> */
    public function getAuthHeaders(): array
    {
        $headers = ['Content-Type' => 'application/json'];

        $authHeaders = match (true) {
            ! empty($this->token) => ['Authorization' => 'Bearer ' . $this->token],
            ! empty($this->email) && ! empty($this->apiKey) => [
                'X-Auth-Email' => $this->email,
                'X-Auth-Key' => $this->apiKey,
            ],
            default => [],
        };

        return [...$headers, ...$authHeaders];
    }

    public function hasCredentials(): bool
    {
        return ! empty($this->token) || (! empty($this->email) && ! empty($this->apiKey));
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }
}
