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
            ':local mmwhurl "http://example.test/hook"; :local mmwhtoken "token"; ',
            '',
            'remove'
        );
        $this->assertStringContainsString('#MMSRV1#', $onlogin);
        $this->assertStringContainsString('mmwhurl', $onlogin);
        $this->assertStringContainsString('/system script add', $onlogin);
        $this->assertStringContainsString('/ip hotspot find where interface=$interface', $onlogin);
        $this->assertStringContainsString('start-date=$dt', $onlogin);
        $this->assertStringContainsString(':if ($uc = "vc" or $uc = "up" or $c = "")', $onlogin);
        $this->assertLessThan(1800, strlen($onlogin));
    }

    public function testCompactRos6NonRecordModeSkipsScriptAndWebhook(): void
    {
        $onlogin = \mikhmon_v3_compact_onlogin(
            1000,
            '1d',
            'mikhmon-rem6',
            ':local mmwhurl "http://example.test/hook"; ',
            '',
            'remove',
            'rem'
        );
        $this->assertStringContainsString(':put (",rem,1000,1d,,,Disable,")', $onlogin);
        $this->assertStringNotContainsString('/system script add', $onlogin);
        $this->assertStringNotContainsString('mmwhurl', $onlogin);
        $this->assertStringContainsString('/system scheduler add', $onlogin);
    }

    public function testRos7OnloginUsesDefinedDateVariableForScheduler(): void
    {
        $onlogin = \mikhmon_v3_onlogin_script(
            'remc',
            '2000',
            '12h',
            '2000',
            'Disable',
            'remove',
            '; :local sname "x"; /system script add name=$sname;',
            ''
        );
        $this->assertStringContainsString('start-date=$date', $onlogin);
        $this->assertStringNotContainsString('start-date=$dt', $onlogin);
    }
}
