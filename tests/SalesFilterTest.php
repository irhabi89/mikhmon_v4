<?php
declare(strict_types=1);
namespace Mikhmon\Tests;

use Mikhmon\Support\SalesFilter;
use PHPUnit\Framework\TestCase;

final class SalesFilterTest extends TestCase
{
    public function testRangeAndMonthLabels(): void
    {
        $meta = SalesFilter::fromQuery(array(
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-30',
            'month_name' => '11',
            'year' => '2026',
            'server' => 'hotspot1',
        ));
        $this->assertSame('2026-11-01', $meta['date_from']);
        $this->assertSame('11', $meta['month_name']);
        $this->assertStringContainsString('01 Nov 2026', SalesFilter::label($meta));
        $this->assertStringContainsString('November 2026', SalesFilter::label($meta));
    }

    public function testSwapsReversedDatesAndRejectsInvalidValues(): void
    {
        $meta = SalesFilter::fromQuery(array(
            'date_from' => '2026-12-31',
            'date_to' => '2026-01-01',
            'month_name' => '13',
            'year' => '20xx',
        ));
        $this->assertSame('2026-01-01', $meta['date_from']);
        $this->assertSame('2026-12-31', $meta['date_to']);
        $this->assertNull($meta['month_name']);
        $this->assertNull($meta['year']);
    }
}
