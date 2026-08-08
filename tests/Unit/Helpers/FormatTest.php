<?php

namespace App\Tests\Unit\Helpers;

use App\Helpers\Format;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    public function testBytesFormatsCorrectly(): void
    {
        $this->assertSame('0 B', Format::bytes(0));
        $this->assertSame('1 B', Format::bytes(1));
        $this->assertSame('1.00 KiB', Format::bytes(1024));
        $this->assertSame('1.50 KiB', Format::bytes(1536));
        $this->assertSame('1.00 MiB', Format::bytes(1048576));
        $this->assertSame('1.00 GiB', Format::bytes(1073741824));
    }

    public function testDateFormatsIso8601(): void
    {
        $this->assertSame('2025-01-15 10:30:00', Format::date('2025-01-15T10:30:00+00:00'));
    }

    public function testDateReturnsOriginalOnInvalid(): void
    {
        $bad = 'not-a-date';
        $this->assertSame($bad, Format::date($bad));
    }

    public function testTimeAgoReturnsCorrectString(): void
    {
        $now = date('c', time() - 30);
        $this->assertStringContainsString('sec ago', Format::timeAgo($now));

        $minAgo = date('c', time() - 120);
        $this->assertStringContainsString('min ago', Format::timeAgo($minAgo));

        $hoursAgo = date('c', time() - 7200);
        $this->assertStringContainsString('hours ago', Format::timeAgo($hoursAgo));

        $daysAgo = date('c', time() - 172800);
        $this->assertStringContainsString('days ago', Format::timeAgo($daysAgo));
    }

    public function testTruncateShortensLongStrings(): void
    {
        $this->assertSame('abc', Format::truncate('abc', 10));
        $this->assertSame('abcdef...', Format::truncate('abcdefghij', 9));
        $this->assertSame('ab', Format::truncate('ab', 5));
    }
}
