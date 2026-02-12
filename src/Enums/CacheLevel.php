<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Enums;

enum CacheLevel: string
{
    case Bypass = 'bypass';
    case Basic = 'basic';
    case Simplified = 'simplified';
    case Aggressive = 'aggressive';
    case CacheEverything = 'cache_everything';

    public function label(): string
    {
        return match ($this) {
            self::Bypass => 'Bypass',
            self::Basic => 'Basic',
            self::Simplified => 'Simplified',
            self::Aggressive => 'Aggressive',
            self::CacheEverything => 'Cache Everything',
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
