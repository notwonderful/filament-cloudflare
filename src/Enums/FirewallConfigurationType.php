<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Enums;

enum FirewallConfigurationType: string
{
    case Ip = 'ip';
    case IpRange = 'ip_range';
    case Country = 'country';

    public function label(): string
    {
        return match ($this) {
            self::Ip => 'IP Address',
            self::IpRange => 'IP Range',
            self::Country => 'Country',
        };
    }

    public function target(): string
    {
        return match ($this) {
            self::Ip => 'ip',
            self::IpRange => 'ip_range',
            self::Country => 'country',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $case) => $case->label(), self::cases())
        );
    }
}
