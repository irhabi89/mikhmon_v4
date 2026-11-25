<?php
declare(strict_types=1);
namespace Mikhmon\Tests;

use Mikhmon\Support\PriceParser;
use Mikhmon\Support\RecordParser;
use PHPUnit\Framework\TestCase;

final class RecordParserTest extends TestCase
{
    public function testParsesLegacyAndRos7Dates(): void
    {
        $legacy = RecordParser::parse('nov/19/2026-|-10:00:00-|-user1-|-5000-|-addr-|-AA:BB-|-30d-|-1mb-|-ok');
        $this->assertNotNull($legacy);
        $this->assertSame('2026-11-19', $legacy['date_key']);
        $this->assertSame('2026-11', $legacy['month_key']);
        $this->assertSame(5000.0, $legacy['price']);

        $ros7 = RecordParser::parse('2026-11-19-|-10:00:00-|-user1-|-5.000-|-addr-|-AA:BB-|-30d-|-1mb-|-ok');
        $this->assertNotNull($ros7);
        $this->assertSame('2026-11-19', $ros7['date_key']);
        $this->assertSame(5000.0, $ros7['price']);
    }

    public function testKeepsCommentContainingSeparator(): void
    {
        $record = RecordParser::parse('nov/19/2026-|-10:00-|-u-|-1000-|-a-|-m-|-1d-|-p-|-note-|-extra');
        $this->assertNotNull($record);
        $this->assertSame('note-|-extra', $record['sale_comment']);
    }

    public function testRejectsMalformedAndInvalidDate(): void
    {
        $this->assertNull(RecordParser::parse('not-a-record'));
        $this->assertNull(RecordParser::parse('feb/30/2026-|-10:00-|-u-|-1-|-a-|-m-|-1d-|-p-|-c'));
    }

    public function testMonthBucketRoundTrip(): void
    {
        $this->assertSame('2026-11', RecordParser::monthKeyFromBucket('nov2026'));
        $this->assertSame('nov2026', RecordParser::bucketFromMonthKey('2026-11'));
        $this->assertSame('2026-12', RecordParser::prefixUpperBound('2026-11'));
        $this->assertSame('nov0', RecordParser::prefixUpperBound('nov/'));
    }

    public function testPriceFormats(): void
    {
        $this->assertSame(5000.0, PriceParser::toFloat('5.000'));
        $this->assertSame(5000.0, PriceParser::toFloat('5,000'));
        $this->assertSame(5.5, PriceParser::toFloat('5,5'));
        $this->assertSame(1234.56, PriceParser::toFloat('1.234,56'));
        $this->assertSame(0.0, PriceParser::toFloat(''));
    }
}
