<?php
declare(strict_types=1);
namespace Mikhmon\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../include/ui.php';

final class UiPaginationTest extends TestCase
{
    public function testPagerHasSingleWrapperAndPerPageFooter(): void
    {
        $html = \mikhmon_pager(120, 2, 50, './?hotspot=hosts&session=s1');
        $this->assertSame(1, substr_count($html, 'mm-pagination-wrap'));
        $this->assertSame(1, substr_count($html, 'mm-pagination-tools'));
        $this->assertSame(1, substr_count($html, 'mm-per-page'));
        $this->assertStringContainsString('Menampilkan', $html);
        $this->assertStringContainsString('per_page=25', $html);
        $this->assertStringContainsString('per_page=50', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
    }

    public function testEmptyPagerIsOmitted(): void
    {
        $this->assertSame('', \mikhmon_pager(0, 1, 50, './?hotspot=hosts'));
    }
}
