<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Enums;

enum FirewallMode: string
{
    case Block = 'block';
    case Challenge = 'challenge';
    case Whitelist = 'whitelist';
    case JsChallenge = 'js_challenge';

    public function label(): string
    {
        return match ($this) {
            self::Block => 'Block',
            self::Challenge => 'Challenge',
            self::Whitelist => 'Whitelist',
            self::JsChallenge => 'JS Challenge',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Block => 'danger',
            self::Challenge => 'warning',
            self::Whitelist => 'success',
            self::JsChallenge => 'info',
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
