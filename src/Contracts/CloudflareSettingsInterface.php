<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Contracts;

interface CloudflareSettingsInterface
{
    public function get(string $key, ?string $default = null): ?string;

    /** @return array<string, string|null> */
    public function getAll(): array;
}
