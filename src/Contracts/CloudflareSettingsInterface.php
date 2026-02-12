<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Contracts;

interface CloudflareSettingsInterface
{
    public function get(string $key, ?string $default = null): ?string;

    public function set(string $key, ?string $value): void;

    /** @return array<string, string|null> */
    public function getAll(): array;

    /** @param array<string, string|null> $settings */
    public function setAll(array $settings): void;
}
