<?php
declare(strict_types=1);
namespace Mikhmon\Tests;

use Mikhmon\Support\ResultCache;
use PHPUnit\Framework\TestCase;

final class ResultCacheTest extends TestCase
{
    private $session = 'cache-test-session';

    protected function tearDown(): void
    {
        ResultCache::flushSession($this->session);
    }

    public function testRememberCachesCompleteData(): void
    {
        $calls = 0;
        $a = ResultCache::remember($this->session, 'key', 30, function () use (&$calls) {
            $calls++;
            return array('rows' => array(1, 2, 3));
        });
        $b = ResultCache::remember($this->session, 'key', 30, function () use (&$calls) {
            $calls++;
            return array('rows' => array(9));
        });
        $this->assertSame(1, $calls);
        $this->assertSame(array(1, 2, 3), $b['data']['rows']);
        $this->assertTrue($b['cached']);
        $this->assertFalse($a['stale']);
    }

    public function testFlushSessionRemovesData(): void
    {
        ResultCache::put($this->session, 'x', array('v' => 1), 30);
        $this->assertSame(array('v' => 1), ResultCache::get($this->session, 'x'));
        ResultCache::flushSession($this->session);
        $this->assertNull(ResultCache::get($this->session, 'x'));
    }
}
