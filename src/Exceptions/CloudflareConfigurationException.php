<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Exceptions;

class CloudflareConfigurationException extends CloudflareException
{
    public static function missingCredentials(): self
    {
        return new self('Cloudflare credentials are not configured. Please set CLOUDFLARE_TOKEN or CLOUDFLARE_EMAIL and CLOUDFLARE_API_KEY.');
    }

    public static function missingZoneId(): self
    {
        return new self('Cloudflare Zone ID is not configured.');
    }

    public static function missingAccountId(): self
    {
        return new self('Cloudflare Account ID is not configured.');
    }
}
