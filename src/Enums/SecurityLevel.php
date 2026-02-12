<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Enums;

enum SecurityLevel: string
{
    case Off = 'off';
    case EssentiallyOff = 'essentially_off';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case UnderAttack = 'under_attack';

    public function label(): string
    {
        return match ($this) {
            self::Off => 'Off',
            self::EssentiallyOff => 'Essentially Off',
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::UnderAttack => 'Under Attack',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Off, self::EssentiallyOff => 'gray',
            self::Low => 'success',
            self::Medium => 'warning',
            self::High, self::UnderAttack => 'danger',
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
