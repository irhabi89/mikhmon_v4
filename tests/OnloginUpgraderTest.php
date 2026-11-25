<?php
declare(strict_types=1);
namespace Mikhmon\Tests;

use Mikhmon\Router\OnloginUpgrader;
use PHPUnit\Framework\TestCase;

final class OnloginUpgraderTest extends TestCase
{
    public function testUpgradesLegacyInlineRecord(): void
    {
        $old = ':local mac $"mac-address"; /system script add name="$date-|-$time-|-$user-|-1000-|-$address-|-$mac-|-1d-|-p-|-$comment" source="$date" comment="mikhmon"';
        $new = OnloginUpgrader::upgradeScript($old);
        $this->assertNotNull($new);
        $this->assertStringContainsString('|#MMSRV1#|$mmsrv', $new);
        $this->assertStringContainsString('/ip/hotspot/active/find', $new);
        $this->assertStringContainsString('/system script add name="$date-|-$time-', $new);
    }

    public function testUpgradesSnameRecord(): void
    {
        $old = ':local sname "$legacy-|-$time-|-$user-|-1000-|-$address-|-$mac-|-1d-|-p-|-$comment"; /system script add name=$sname comment=mikhmon';
        $new = OnloginUpgrader::upgradeScript($old);
        $this->assertNotNull($new);
        $this->assertStringContainsString(':local mmsrv', $new);
        $this->assertStringContainsString('|#MMSRV1#|$mmsrv";', $new);
    }

    public function testAlreadyUpgradedAndCustomScriptsAreSkipped(): void
    {
        $this->assertNull(OnloginUpgrader::upgradeScript(':local sname "x|#MMSRV1#|$mmsrv";'));
        $this->assertNull(OnloginUpgrader::upgradeScript(':put "custom";'));
    }
}
