<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\DataTransferObjects;

use notwonderful\FilamentCloudflare\Enums\FirewallConfigurationType;
use notwonderful\FilamentCloudflare\Enums\FirewallMode;

final readonly class FirewallRuleData
{
    public function __construct(
        public FirewallMode $mode,
        public FirewallConfigurationType $configurationType,
        public string $value,
        public ?string $notes = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $mode = $data['mode'];
        $configType = $data['configuration_type'];

        return new self(
            mode: $mode instanceof FirewallMode ? $mode : FirewallMode::from($mode),
            configurationType: $configType instanceof FirewallConfigurationType ? $configType : FirewallConfigurationType::from($configType),
            value: self::extractValue($data),
            notes: $data['notes'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toApiPayload(): array
    {
        $payload = [
            'mode' => $this->mode->value,
            'configuration' => [
                'target' => $this->configurationType->target(),
                'value' => $this->value,
            ],
        ];

        if ($this->notes !== null) {
            $payload['notes'] = $this->notes;
        }

        return $payload;
    }

    /** @param array<string, mixed> $data */
    private static function extractValue(array $data): string
    {
        $type = $data['configuration_type'];
        $configType = $type instanceof FirewallConfigurationType ? $type : FirewallConfigurationType::from($type);

        return match ($configType) {
            FirewallConfigurationType::Ip => $data['ip'],
            FirewallConfigurationType::IpRange => $data['ip_range'],
            FirewallConfigurationType::Country => $data['country'],
        };
    }
}
