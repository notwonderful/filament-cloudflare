<?php

declare(strict_types=1);

namespace Tests\Unit;

use notwonderful\FilamentCloudflare\Enums\DnsRecordType;
use PHPUnit\Framework\TestCase;

class DnsRecordTypeTest extends TestCase
{
    public function test_all_cases_have_labels(): void
    {
        foreach (DnsRecordType::cases() as $case) {
            $this->assertNotEmpty($case->label(), "DnsRecordType::{$case->name} has no label");
        }
    }

    public function test_all_cases_have_colors(): void
    {
        foreach (DnsRecordType::cases() as $case) {
            $this->assertNotEmpty($case->color(), "DnsRecordType::{$case->name} has no color");
        }
    }

    public function test_options_returns_all_cases(): void
    {
        $options = DnsRecordType::options();

        $this->assertCount(count(DnsRecordType::cases()), $options);
        $this->assertArrayHasKey('A', $options);
        $this->assertArrayHasKey('AAAA', $options);
        $this->assertArrayHasKey('CNAME', $options);
        $this->assertArrayHasKey('MX', $options);
        $this->assertArrayHasKey('TXT', $options);
    }

    public function test_commonOptions_returns_subset(): void
    {
        $common = DnsRecordType::commonOptions();

        $this->assertCount(8, $common);
        $this->assertArrayHasKey('A', $common);
        $this->assertArrayHasKey('AAAA', $common);
        $this->assertArrayHasKey('CNAME', $common);
        $this->assertArrayHasKey('MX', $common);
        $this->assertArrayHasKey('TXT', $common);
        $this->assertArrayHasKey('NS', $common);
        $this->assertArrayHasKey('SRV', $common);
        $this->assertArrayHasKey('CAA', $common);
    }

    public function test_supportsProxy_only_for_a_aaaa_cname(): void
    {
        $this->assertTrue(DnsRecordType::A->supportsProxy());
        $this->assertTrue(DnsRecordType::AAAA->supportsProxy());
        $this->assertTrue(DnsRecordType::CNAME->supportsProxy());

        $this->assertFalse(DnsRecordType::MX->supportsProxy());
        $this->assertFalse(DnsRecordType::TXT->supportsProxy());
        $this->assertFalse(DnsRecordType::NS->supportsProxy());
        $this->assertFalse(DnsRecordType::SRV->supportsProxy());
        $this->assertFalse(DnsRecordType::CAA->supportsProxy());
    }

    public function test_requiresPriority_only_for_mx_srv_uri(): void
    {
        $this->assertTrue(DnsRecordType::MX->requiresPriority());
        $this->assertTrue(DnsRecordType::SRV->requiresPriority());
        $this->assertTrue(DnsRecordType::URI->requiresPriority());

        $this->assertFalse(DnsRecordType::A->requiresPriority());
        $this->assertFalse(DnsRecordType::CNAME->requiresPriority());
        $this->assertFalse(DnsRecordType::TXT->requiresPriority());
    }

    public function test_labels_are_human_readable(): void
    {
        $this->assertEquals('A (IPv4)', DnsRecordType::A->label());
        $this->assertEquals('AAAA (IPv6)', DnsRecordType::AAAA->label());
        $this->assertEquals('CNAME (Alias)', DnsRecordType::CNAME->label());
        $this->assertEquals('MX (Mail)', DnsRecordType::MX->label());
        $this->assertEquals('TXT (Text)', DnsRecordType::TXT->label());
    }

    public function test_tryFrom_returns_null_for_unknown(): void
    {
        $this->assertNull(DnsRecordType::tryFrom('UNKNOWN'));
    }
}
