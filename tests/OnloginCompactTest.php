<?php
declare(strict_types=1);
namespace Mikhmon\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../include/mikhmon_scripts.php';

final class OnloginCompactTest extends TestCase
{
    public function testCompactRos6OnloginContainsServerWebhookAndFallback(): void
    {
        $onlogin = \mikhmon_v3_compact_onlogin(
            1000,
            '1d',
            'mikhmon-test6',
            ':local mmwhurl "http://example.test/hook"; :local mmwhtoken "token"; '
        );
        $this->assertStringContainsString('#MMSRV1#', $onlogin);
        $this->assertStringContainsString('mmwhurl', $onlogin);
        $this->assertStringContainsString('/system script add', $onlogin);
        $this->assertStringContainsString('/ip hotspot find where interface=$interface', $onlogin);
        $this->assertLessThan(1800, strlen($onlogin));
    }
}
