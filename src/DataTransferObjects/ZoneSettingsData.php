<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\DataTransferObjects;

use notwonderful\FilamentCloudflare\Enums\MinTlsVersion;
use notwonderful\FilamentCloudflare\Enums\SecurityLevel;
use notwonderful\FilamentCloudflare\Enums\SslMode;

final readonly class ZoneSettingsData
{
    private const BOOLEAN_SETTINGS = ['always_use_https', 'automatic_https_rewrites', 'browser_check'];

    public function __construct(
        public ?SecurityLevel $securityLevel = null,
        public ?SslMode $ssl = null,
        public ?bool $alwaysUseHttps = null,
        public ?bool $automaticHttpsRewrites = null,
        public ?bool $browserCheck = null,
        public ?MinTlsVersion $minTlsVersion = null,
    ) {}

    /** @param array<int, array<string, mixed>> $settings */
    public static function fromApiResponse(array $settings): self
    {
        $data = [];
        
        foreach ($settings as $setting) {
            $id = $setting['id'] ?? null;
            $value = $setting['value'] ?? null;
            
            if (!$id) {
                continue;
            }
            
            // Для boolean настроек обрабатываем даже если значение null (может быть false)
            if (in_array($id, self::BOOLEAN_SETTINGS, true)) {
                $data[$id] = self::normalizeBoolean($value);
                continue;
            }
            
            if ($value === null) {
                continue;
            }

            $data[$id] = match (true) {
                $id === 'security_level' && is_string($value) => self::tryEnum(SecurityLevel::class, $value) ?? $value,
                $id === 'ssl' && is_string($value) => self::tryEnum(SslMode::class, $value) ?? $value,
                $id === 'min_tls_version' && is_string($value) => self::tryEnum(MinTlsVersion::class, $value) ?? $value,
                default => $value,
            };
        }

        return new self(
            securityLevel: $data['security_level'] ?? null,
            ssl: $data['ssl'] ?? null,
            alwaysUseHttps: $data['always_use_https'] ?? null,
            automaticHttpsRewrites: $data['automatic_https_rewrites'] ?? null,
            browserCheck: $data['browser_check'] ?? null,
            minTlsVersion: $data['min_tls_version'] ?? null,
        );
    }

    /** @return array<string, array<string, mixed>> */
    public function toApiPayload(): array
    {
        $payload = [];

        $this->securityLevel && $payload['security_level'] = ['value' => $this->securityLevel->value];
        $this->ssl && $payload['ssl'] = ['value' => $this->ssl->value];
        $this->alwaysUseHttps !== null && $payload['always_use_https'] = ['value' => $this->alwaysUseHttps ? 'on' : 'off'];
        $this->automaticHttpsRewrites !== null && $payload['automatic_https_rewrites'] = ['value' => $this->automaticHttpsRewrites ? 'on' : 'off'];
        $this->browserCheck !== null && $payload['browser_check'] = ['value' => $this->browserCheck ? 'on' : 'off'];
        $this->minTlsVersion && $payload['min_tls_version'] = ['value' => $this->minTlsVersion->value];

        return $payload;
    }

    private static function normalizeBoolean(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        // Cloudflare может возвращать разные форматы: 'on', 'off', true, false, 1, 0, '1', '0'
        return match (true) {
            $value === 'on',
            $value === true,
            $value === 1,
            $value === '1',
            strtolower((string) $value) === 'on',
            strtolower((string) $value) === 'true' => true,
            default => false,
        };
    }

    private static function tryEnum(string $enumClass, string $value): ?\BackedEnum
    {
        try {
            return $enumClass::from($value);
        } catch (\ValueError) {
            return null;
        }
    }
}
