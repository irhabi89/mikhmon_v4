<?php
declare(strict_types=1);
namespace Mikhmon\Tests;

use Mikhmon\Support\Pagination;
use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase
{
    public function testWindow(): void
    {
        $this->assertSame(array(1), Pagination::window(1, 1));
        $this->assertSame(array(1, 2, 3, 4, 5, '...', 20), Pagination::window(3, 20));
        $this->assertSame(array(1, '...', 17, 18, 19, 20), Pagination::window(19, 20));
    }

    public function testPageSizeAllowlist(): void
    {
        $this->assertSame(50, Pagination::perPage(999));
        $this->assertSame(100, Pagination::perPage(100));
    }
}
