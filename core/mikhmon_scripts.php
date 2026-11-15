<?php
/**
 * Generate hotspot user-profile on-login script.
 *
 * RouterOS 6 returns dates as "nov/15/2026", while RouterOS 7 returns
 * "2026-11-15". Mikhmon report/expiry logic expects the RouterOS 6 form,
 * so the generated script normalises both formats to the legacy form.
 */
function mikhmon_onlogin_script($expmode, $price, $validity, $sprice, $getlock, $srvlock, $mode, $record, $lock, $slock)
{
    $put = ':put (",' . $expmode . ',' . $price . ',' . $validity . ',' . $sprice . ',,' . $getlock . ',' . $srvlock . ',")';

    if ($expmode == "0" && $price != "") {
        return $put . $lock . $slock;
    }

    if (!in_array($expmode, array("rem", "ntf", "remc", "ntfc"))) {
        return "";
    }

    $logic = <<<'ROS'
:local date [ /system clock get date ];
:local time [ /system clock get time ];
:local mnames "janfebmaraprmayjunjulaugsepoctnovdec";
:local year;
:local monthname;
:local day;
:if ([:pick $date 4 5] = "-") do={
  :set year [:pick $date 0 4];
  :set day [:pick $date 8 10];
  :local mnum1 [:tonum [:pick $date 5 7]];
  :set monthname [:pick $mnames (($mnum1 - 1) * 3) (($mnum1 - 1) * 3 + 3)];
} else={
  :set year [:pick $date 7 11];
  :set day [:pick $date 4 6];
  :set monthname [:pick $date 0 3];
}
:local legacy "$monthname/$day/$year";
:local comment [ /ip hotspot user get [ /ip hotspot user find where name="$user" ] comment ];
:local ucode [:pic $comment 0 2];
:if ($ucode = "vc" or $ucode = "up" or $comment = "") do={
  /sys sch add name="$user" disable=no start-date=$date interval="{{validity}}";
  :delay 2s;
  :local exp [ /sys sch get [ /sys sch find where name="$user" ] next-run ];
  :local expLegacy;
  :local getxp [len $exp];
  :if ($getxp = 8) do={
    :set expLegacy "$legacy $exp";
  } else={
    :if ($getxp = 15) do={
      :local d [:pic $exp 0 6];
      :local t [:pic $exp 7 16];
      :set expLegacy "$d/$year $t";
    } else={
      :if ([:pick $exp 4 5] = "-") do={
        :local ey [:pic $exp 0 4];
        :local ed [:pic $exp 8 10];
        :local mnum2 [:tonum [:pic $exp 5 7]];
        :local emn [:pick $mnames (($mnum2 - 1) * 3) (($mnum2 - 1) * 3 + 3)];
        :local et [:pic $exp 11 [:len $exp]];
        :set expLegacy "$emn/$ed/$ey $et";
      } else={
        :set expLegacy $exp;
      }
    }
  }
  /ip hotspot user set comment="$expLegacy $mode" [find where name="$user"];
  /sys sch remove [find where name="$user"];
ROS;

    $logic = str_replace('{{validity}}', $validity, $logic);
    // The bundled RouterOS API splits every parameter on newlines, so the
    // generated on-login script must stay on a single line. A closing brace
    // needs an explicit separator before the next statement.
    $logic = preg_replace('/\s+/', ' ', $logic);
    $logic = preg_replace('~}\s+(?=[:/])~', '}; ', $logic);

    $record_part = ($expmode == "remc" || $expmode == "ntfc") ? $record : "";

    return $put . '; :local mode "' . $mode . '"; {' . $logic . $record_part . $lock . $slock . '}}';
}
