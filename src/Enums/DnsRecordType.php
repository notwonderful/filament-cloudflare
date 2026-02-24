<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Enums;

enum DnsRecordType: string
{
    case A = 'A';
    case AAAA = 'AAAA';
    case CNAME = 'CNAME';
    case MX = 'MX';
    case TXT = 'TXT';
    case NS = 'NS';
    case SRV = 'SRV';
    case CAA = 'CAA';
    case PTR = 'PTR';
    case LOC = 'LOC';
    case CERT = 'CERT';
    case DNSKEY = 'DNSKEY';
    case DS = 'DS';
    case HTTPS = 'HTTPS';
    case NAPTR = 'NAPTR';
    case SMIMEA = 'SMIMEA';
    case SSHFP = 'SSHFP';
    case SVCB = 'SVCB';
    case TLSA = 'TLSA';
    case URI = 'URI';

    public function label(): string
    {
        return match ($this) {
            self::A => 'A (IPv4)',
            self::AAAA => 'AAAA (IPv6)',
            self::CNAME => 'CNAME (Alias)',
            self::MX => 'MX (Mail)',
            self::TXT => 'TXT (Text)',
            self::NS => 'NS (Nameserver)',
            self::SRV => 'SRV (Service)',
            self::CAA => 'CAA (CA Auth)',
            self::PTR => 'PTR (Pointer)',
            default => $this->value,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::A, self::AAAA => 'success',
            self::CNAME => 'info',
            self::MX => 'warning',
            self::TXT => 'gray',
            self::NS => 'primary',
            self::SRV => 'purple',
            self::CAA => 'danger',
            default => 'gray',
        };
    }

    public function supportsProxy(): bool
    {
        return in_array($this, [self::A, self::AAAA, self::CNAME], true);
    }

    public function requiresPriority(): bool
    {
        return in_array($this, [self::MX, self::SRV, self::URI], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $case) => $case->label(), self::cases()),
        );
    }

    /** @return array<string, string> */
    public static function commonOptions(): array
    {
        $common = [self::A, self::AAAA, self::CNAME, self::MX, self::TXT, self::NS, self::SRV, self::CAA];

        return array_combine(
            array_map(fn (self $case) => $case->value, $common),
            array_map(fn (self $case) => $case->label(), $common),
        );
    }
}
