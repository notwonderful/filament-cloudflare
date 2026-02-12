<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Enums;

enum AnalyticsDaysRange: int
{
    case Last24Hours = 1;
    case Last7Days = 7;
    case Last30Days = 30;
    case Last90Days = 90;

    public function label(): string
    {
        return match ($this) {
            self::Last24Hours => 'Last 24 hours',
            self::Last7Days => 'Last 7 days',
            self::Last30Days => 'Last 30 days',
            self::Last90Days => 'Last 90 days',
        };
    }

    /** @return array<int, string> */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $case) => $case->label(), self::cases())
        );
    }

    public static function default(): self
    {
        return self::Last24Hours;
    }
}
