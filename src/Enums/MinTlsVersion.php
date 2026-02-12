<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Enums;

enum MinTlsVersion: string
{
    case V1_0 = '1.0';
    case V1_1 = '1.1';
    case V1_2 = '1.2';
    case V1_3 = '1.3';

    public function label(): string
    {
        return $this->value;
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
