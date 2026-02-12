<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Enums;

enum SslMode: string
{
    case Off = 'off';
    case Flexible = 'flexible';
    case Full = 'full';
    case Strict = 'strict';

    public function label(): string
    {
        return match ($this) {
            self::Off => 'Off',
            self::Flexible => 'Flexible',
            self::Full => 'Full',
            self::Strict => 'Strict',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Off => 'gray',
            self::Flexible => 'warning',
            self::Full, self::Strict => 'success',
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
