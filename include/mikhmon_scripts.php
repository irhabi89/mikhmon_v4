<?php
/**
 * Generate hotspot user-profile on-login script and background service scheduler
 * for Mikhmon v3 with dual RouterOS v6 and v7 compatibility.
 */

function mikhmon_v3_onlogin_script($expmode, $price, $validity, $sprice, $getlock, $mode, $record, $lock)
{
    $put = ':put (",' . $expmode . ',' . $price . ',' . $validity . ',' . $sprice . ',,' . $getlock . ',")';

    if ($expmode == "0" && $price != "") {
        return ':put (",,' . $price . ',,,noexp,' . $getlock . ',")' . $lock;
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
:local ucode [:pick $comment 0 2];
:if ($ucode = "vc" or $ucode = "up" or $comment = "") do={
  /sys sch add name="$user" disable=no start-date=$dt interval="{{validity}}";
  :delay 2s;
  :local exp [ /sys sch get [ /sys sch find where name="$user" ] next-run ];
  :local expLegacy;
  :local getxp [len $exp];
  :if ($getxp = 8) do={
    :set expLegacy "$legacy $exp";
  } else={
    :if ($getxp = 15) do={
      :local d [:pick $exp 0 6];
      :local t [:pick $exp 7 16];
      :set expLegacy "$d/$year $t";
    } else={
      :if ([:pick $exp 4 5] = "-") do={
        :local ey [:pick $exp 0 4];
        :local ed [:pick $exp 8 10];
        :local mnum2 [:tonum [:pick $exp 5 7]];
        :local emn [:pick $mnames (($mnum2 - 1) * 3) (($mnum2 - 1) * 3 + 3)];
        :local et [:pick $exp 11 [:len $exp]];
        :set expLegacy "$emn/$ed/$ey $et";
      } else={
        :set expLegacy $exp;
      }
    }
  }
  /ip hotspot user set comment="$expLegacy" [find where name="$user"];
  /sys sch remove [find where name="$user"];
ROS;

    $logic = str_replace('{{validity}}', $validity, $logic);
    $logic = preg_replace('/\s+/', ' ', $logic);
    $logic = preg_replace('~}\s+(?=[:/])~', '}; ', $logic);

    $record_part = ($expmode == "remc" || $expmode == "ntfc") ? $record : "";

    return $put . '; {' . $logic . $record_part . $lock . '}}';
}

function mikhmon_v3_bgservice_script($name, $mode)
{
    $bg = <<<'ROS'
:local normdate do={
  :local mnames "janfebmaraprmayjunjulaugsepoctnovdec";
  :local v $d;
  :if ([:pick $v 4 5] = "-") do={
    :local mn [:tonum [:pick $v 5 7]];
    :local m [:pick $mnames (($mn - 1) * 3) (($mn - 1) * 3 + 3)];
    :local rest "";
    :if ([:len $v] > 10) do={:set rest [:pick $v 10 [:len $v]]};
    :local mm [:pick $v 8 10];
    :local yy [:pick $v 0 4];
    :set v "$m/$mm/$yy$rest";
  };
  :return $v;
};
:local dateint do={
  :local mnames "janfebmaraprmayjunjulaugsepoctnovdec";
  :local days [:pick $d 4 6];
  :local month [:pick $d 0 3];
  :local year [:pick $d 7 11];
  :local monthint ([:find $mnames $month] / 3);
  :local monthnum ($monthint + 1);
  :if ([:len $monthnum] = 1) do={
    :local zero "0";
    :return [:tonum "$year$zero$monthnum$days"];
  } else={
    :return [:tonum "$year$monthnum$days"];
  };
};
:local timeint do={
  :local hours [:pick $t 0 2];
  :local minutes [:pick $t 3 5];
  :return ($hours * 60 + $minutes);
};
:local date [/system clock get date];
:local time [/system clock get time];
:local todaydate [$normdate d=$date];
:local today [$dateint d=$todaydate];
:local curtime [$timeint t=$time];
:foreach i in [/ip hotspot user find where profile="{{profile_name}}"] do={
  :local comment [/ip hotspot user get $i comment];
  :local name [/ip hotspot user get $i name];
  :local norm [$normdate d=$comment];
  :if ([:pick $norm 3] = "/" and [:pick $norm 6] = "/" and [:len $norm] >= 19) do={
    :local gettime [:pick $norm 12 20];
    :local expd [$dateint d=$norm];
    :local expt [$timeint t=$gettime];
    :if ($expd < $today or ($expd = $today and $expt < $curtime)) do={
      [ /ip hotspot user {{mode}} $i ];
      [ /ip hotspot active remove [find where user=$name] ];
    };
  };
};
ROS;

    $bg = str_replace('{{profile_name}}', $name, $bg);
    $bg = str_replace('{{mode}}', $mode, $bg);
    $bg = preg_replace('/\s+/', ' ', $bg);
    $bg = preg_replace('~}\s+(?=[:/])~', '}; ', $bg);

    return $bg;
}

if (!function_exists('mikhmon_is_ros6')) {
    function mikhmon_is_ros6($api): bool
    {
        try {
            $res = $api->comm('/system/resource/print');
            $version = isset($res[0]['version']) ? (string)$res[0]['version'] : '';
            if (preg_match('/^(\d+)\.(\d+)/', $version, $m) !== 1) {
                return false;
            }
            return (int)$m[1] < 7;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('mikhmon_v3_compact_onlogin')) {
    /**
     * Compact on-login for legacy RouterOS 6, whose profile on-login field
     * rejects the full expiry logic once a webhook is added.
     *
     * Expiry is installed as a per-user RouterOS scheduler instead of the
     * long inline next-run/comment logic used by the ROS7 profile script.
     */
    function mikhmon_v3_compact_onlogin($price, $validity, $profile, string $webhookPart, string $lock = '', string $mode = ''): string
    {
        $expiry = '';
        if ($mode !== '') {
            $action = $mode === 'remove'
                ? '("/ip hotspot user remove [find name=" . $user . "]; /ip hotspot active remove [find user=" . $user . "]")'
                : '("/ip hotspot user set [find name=" . $user . "] limit-uptime=1s")';
            $expiry = ' :if ([:len [/system scheduler find where name=$user]] > 0) do={/system scheduler remove [find where name=$user]}; '
                . ':local ev ' . $action . '; '
                . '/system scheduler add name=$user start-date=$dt interval="' . $validity . '" on-event=$ev comment="Mikhmon expiry"; ';
        }
        return '; :local sv "$server"; '
            . ':if ([:len $sv] = 0) do={:local h [/ip hotspot find where interface=$interface]; :if ([:len $h] > 0) do={:set sv [/ip hotspot get $h name]}}; '
            . ':local dt [/system clock get date]; :local tm [/system clock get time]; '
            . ':local sname "$dt-|-$tm-|-$user-|-' . $price . '-|-$address-|-$mac-|-' . $validity . '-|-' . $profile . '-|-$comment|#MMSRV1#|$sv"; '
            . '/system script add name=$sname source="compact" comment=mikhmon; '
            . $expiry . $webhookPart . $lock;
    }
}
