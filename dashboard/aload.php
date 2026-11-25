<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
session_start();
// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
// load session MikroTik
  $session = $_GET['session'];
  $load = $_GET['load'];

// lang
include('../include/lang.php');
include('../lang/'.$langid.'.php');

// load config
  include('../include/config.php');
  include('../include/readcfg.php');

  // Do not hold the PHP session lock while talking to the router.
  $need_logging_setup = ($load === 'logs') && empty($_SESSION[$session.'logging_disk']);
  session_write_close();

// routeros api
  require_once(__DIR__ . '/../include/bootstrap.php');
  include_once('../lib/routeros_api.class.php');
  include_once('../lib/router.php');
  include_once('../lib/formatbytesbites.php');
  $API = new RouterosAPI();
  $API->debug = false;



  if ($load == "sysresource") {

    $API->connect($iphost, $userhost, decrypt($passwdhost));

// get MikroTik system clock
    include_once('../include/timezone.php');
    $getclock = $API->comm("/system/clock/print");
    $clock = isset($getclock[0]) ? $getclock[0] : array();
    $timezone = mikhmon_timezone_from_clock($clock);
    date_default_timezone_set($timezone);

// get system resource MikroTik
    $getresource = $API->comm("/system/resource/print");
    $resource = isset($getresource[0]) ? $getresource[0] : array();

// get routeboard info (with fallback for CHR)
    $getrouterboard = $API->comm("/system/routerboard/print");
    $routerboard = isset($getrouterboard[0]) ? $getrouterboard[0] : array();
    $board_model = isset($routerboard['model']) ? $routerboard['model'] : '';
    if ($board_model === '' && isset($resource['board-name'])) {
      $board_model = $resource['board-name'];
    }
    ?>
    
    <div id="r_1" class="row">
      <div class="col-4">
        <div class="box bmh-75 box-bordered">
          <div class="box-group">
            <div class="box-group-icon"><i class="fa fa-calendar"></i></div>
              <div class="box-group-area">
              <span ><?= $_system_date_time ?><br>
                    <?php 
                    echo ucfirst($clock['date']) . " " . $clock['time'] . "<br>
                    ".$_uptime." : " . formatDTM($resource['uptime']);
                    ?>
                </span>
              </div>
            </div>
          </div>
        </div>
      <div class="col-4">
        <div class="box bmh-75 box-bordered">
          <div class="box-group">
          <div class="box-group-icon"><i class="fa fa-info-circle"></i></div>
              <div class="box-group-area">
                <span >
                    <?php
                    echo $_board_name." : " . (isset($resource['board-name']) ? $resource['board-name'] : '') . "<br/>
                    ".$_model." : " . $board_model . "<br/>
                    Router OS : " . (isset($resource['version']) ? $resource['version'] : '');
                    ?>
                </span>
              </div>
            </div>
          </div>
        </div>
    <div class="col-4">
      <div class="box bmh-75 box-bordered">
        <div class="box-group">
          <div class="box-group-icon"><i class="fa fa-server"></i></div>
              <div class="box-group-area">
                <span >
                    <?php
                    echo $_cpu_load." : " . $resource['cpu-load'] . "%<br/>
                    ".$_free_memory." : " . formatBytes($resource['free-memory'], 2) . "<br/>
                    ".$_free_hdd." : " . formatBytes($resource['free-hdd-space'], 2)
                    ?>
                </span>
                </div>
              </div>
            </div>
          </div> 
      </div>

<?php 
} else if ($load == "hotspot") {

  $API->connect($iphost, $userhost, decrypt($passwdhost));
// get & counting hotspot users
  $countallusers = $API->comm("/ip/hotspot/user/print", array("count-only" => ""));
  if ($countallusers < 2) {
    $uunit = "item";
  } elseif ($countallusers > 1) {
    $uunit = "items";
  }

// get & counting hotspot active
  $counthotspotactive = $API->comm("/ip/hotspot/active/print", array("count-only" => ""));
  if ($counthotspotactive < 2) {
    $hunit = "item";
  } elseif ($counthotspotactive > 1) {
    $hunit = "items";
  }

  ?>
    
            <div id="r_2" class="card">
              <div class="card-header"><h3><i class="fa fa-wifi"></i> Hotspot</h3></div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-3 col-box-6">
                      <div class="box bg-blue bmh-75">
                        <a href="./?hotspot=active&session=<?= $session; ?>">
                          <h1><?= $counthotspotactive; ?>
                              <span style="font-size: 15px;"><?= $hunit; ?></span>
                            </h1>
                          <div>
                            <i class="fa fa-laptop"></i> <?= $_hotspot_active ?>
                          </div>
                        </a>
                      </div>
                    </div>
                    <div class="col-3 col-box-6">
                    <div class="box bg-green bmh-75">
                      <a href="./?hotspot=users&profile=all&session=<?= $session; ?>">
                            <h1><?= $countallusers; ?>
                              <span style="font-size: 15px;"><?= $uunit; ?></span>
                            </h1>
                      <div>
                            <i class="fa fa-users"></i> <?= $_hotspot_users ?>
                          </div>
                      </a>
                    </div>
                  </div>
                  <div class="col-3 col-box-6">
                    <div class="box bg-yellow bmh-75">
                      <a href="./?hotspot-user=add&session=<?= $session; ?>">
                        <div>
                          <h1><i class="fa fa-user-plus"></i>
                              <span style="font-size: 15px;"><?= $_add ?></span>
                          </h1>
                        </div>
                        <div>
                            <i class="fa fa-user-plus"></i> <?= $_hotspot_users ?>
                        </div>
                      </a>
                    </div>
                  </div>
                  <div class="col-3 col-box-6">
                    <div class="box bg-red bmh-75">
                      <a href="./?hotspot-user=generate&session=<?= $session; ?>">
                        <div>
                          <h1><i class="fa fa-user-plus"></i>
                              <span style="font-size: 15px;"><?= $_generate ?></span>
                          </h1>
                        </div>
                        <div>
                            <i class="fa fa-user-plus"></i> <?= $_hotspot_users ?>
                        </div>
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
          </div>

<?php
} else if ($load == "servers") {
  try {
    $API->connect($iphost, $userhost, decrypt($passwdhost));
    $hotspotRead = mikhmon_router_cached($API, $session, '/ip/hotspot/print', array('.proplist' => 'name'), 20);
    $activeRead = mikhmon_router_cached($API, $session, '/ip/hotspot/active/print', array('.proplist' => 'server'), 15);
    $usersRead = mikhmon_router_cached($API, $session, '/ip/hotspot/user/print', array('.proplist' => 'server'), 15);

    $dashSalesStats = array();
    $salesRepo = new \Mikhmon\Storage\SalesRepository(mikhmon_database());
    foreach ($salesRepo->serverStats($session) as $row) {
      $dashSalesStats[(string)$row['server_name']] = $row;
    }
    $serverMap = array();
    foreach ((array)$hotspotRead['data'] as $srv) {
      $name = isset($srv['name']) ? trim((string)$srv['name']) : '';
      if ($name !== '') { $serverMap[$name] = array('name' => $name, 'active' => 0, 'users' => 0); }
    }
    foreach ((array)$activeRead['data'] as $act) {
      $name = isset($act['server']) ? trim((string)$act['server']) : '';
      if ($name === '') { continue; }
      if (!isset($serverMap[$name])) { $serverMap[$name] = array('name' => $name, 'active' => 0, 'users' => 0); }
      $serverMap[$name]['active']++;
    }
    foreach ((array)$usersRead['data'] as $usr) {
      $name = isset($usr['server']) ? trim((string)$usr['server']) : '';
      if ($name === '' || $name === 'all') { continue; }
      if (!isset($serverMap[$name])) { $serverMap[$name] = array('name' => $name, 'active' => 0, 'users' => 0); }
      $serverMap[$name]['users']++;
    }
    foreach ($dashSalesStats as $name => $row) {
      if ($name === '__none__') { continue; }
      if (!isset($serverMap[$name])) { $serverMap[$name] = array('name' => $name, 'active' => 0, 'users' => 0); }
    }
    if (isset($dashSalesStats['__none__'])) {
      $serverMap['__none__'] = array('name' => '__none__', 'active' => 0, 'users' => 0);
    }
    $serverRows = array();
    foreach ($serverMap as $name => $base) {
      $stats = isset($dashSalesStats[$name]) ? $dashSalesStats[$name] : null;
      $serverRows[] = array(
        'name' => $name,
        'active' => (int)$base['active'],
        'users' => (int)$base['users'],
        'today_count' => $stats ? (int)$stats['today_count'] : 0,
        'today_sum' => $stats ? (float)$stats['today_sum'] : 0.0,
        'month_count' => $stats ? (int)$stats['month_count'] : 0,
        'month_sum' => $stats ? (float)$stats['month_sum'] : 0.0,
      );
    }
    $dash_spr_indo = (isset($cekindo['indo']) && is_array($cekindo['indo'])) ? $cekindo['indo'] : array('Rp');
    $dash_spr_dec = in_array((string)(isset($currency) ? $currency : 'Rp'), $dash_spr_indo) ? 0 : 2;
    $dash_spr_fmt = function ($v) use ($currency, $dash_spr_dec) {
      return $currency . ' ' . number_format((float)$v, $dash_spr_dec, ',', '.');
    };
    \Mikhmon\Http\View::render('components/server-cards', array(
      'rows' => $serverRows,
      'formatMoney' => $dash_spr_fmt,
      'session' => $session,
      'cachedAt' => max((int)$hotspotRead['created_at'], (int)$activeRead['created_at'], (int)$usersRead['created_at']),
    ));
  } catch (\Throwable $e) {
    echo '<div class="card" style="margin-top:10px;"><div class="card-header"><h3><i class="fa fa-server"></i> Per Server</h3></div><div class="card-body"><span class="text-red">Data server gagal dimuat: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '. <a href="?session=' . urlencode((string)$session) . '&refresh=1">Coba lagi</a></span></div></div>';
  }
} else if ($load == "logs") {

  $API->connect($iphost, $userhost, decrypt($passwdhost));

  // check/move hotspot log to disk once per session instead of every 10s
  if ($need_logging_setup) {
    $getlogging = $API->comm("/system/logging/print", array("?prefix" => "->", ".proplist" => ".id,prefix"));
    if (!(isset($getlogging[0]['prefix']) && $getlogging[0]['prefix'] == "->")) {
      $API->comm("/system/logging/add", array("action" => "disk", "prefix" => "->", "topics" => "hotspot,info,debug"));
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
      @session_start();
    }
    $_SESSION[$session.'logging_disk'] = true;
    session_write_close();
  }

  // get hotspot log with light proplist to save WAN bandwidth
  $getlog = $API->comm("/log/print", array("?topics" => "hotspot,info,debug", ".proplist" => "time,message"));
  $log = is_array($getlog) ? array_reverse($getlog) : array();
  //$THotspotLog = count($getlog);

  if ($livereport == "disable") {
    $logh = "457px";
    $lreport = "style='display:none;'";
  } else {
    $logh = "350px";
    $lreport = "style='display:block;'";
  }



  ?>
  
              <div id="r_3" class="row">
              <div class="card">
                <div class="card-header">
                  <h3><a href="./?hotspot=log&session=<?= $session; ?>" title="Open Hotspot Log" ><i class="fa fa-align-justify"></i> <?= $_hotspot_log ?></a></h3></div>
                    <div class="card-body">
                      <div style="padding: 5px; height: <?= $logh; ?> ;" class="mr-t-10 overflow">
                        <table class="table table-sm table-bordered table-hover" style="font-size: 12px; td.padding:2px;">
                          <thead>
                            <tr>
                            <th><?= $_time .$THotspotLog; ?></th>
                            <th><?= $_users ?> (IP)</th>
                            <th><?= $_messages ?></th>
                            </tr>
                          </thead>
                          <tbody>
                      
  <?php


  for ($i = 0; $i < 20; $i++) {
    $mess = explode(":", $log[$i]['message']);
    $time = $log[$i]['time'];
    echo "<tr>";
    if (substr($log[$i]['message'], 0, 2) == "->") {
      echo "<td>" . $time . "</td>";
    //echo substr($mess[1], 0,2);
      echo "<td>";
      if (count($mess) > 6) {
        echo $mess[1] . ":" . $mess[2] . ":" . $mess[3] . ":" . $mess[4] . ":" . $mess[5] . ":" . $mess[6];
      } else {
        echo $mess[1];
      }
      echo "</td>";
      echo "<td>";
      if (count($mess) > 6) {
        echo str_replace("trying to", "", $mess[7] . " " . $mess[8] . " " . $mess[9] . " " . $mess[10]);
      } else {
        echo str_replace("trying to", "", $mess[2] . " " . $mess[3] . " " . $mess[4] . " " . $mess[5]);
      }
      echo "</td>";
    } else {
    }
    echo "</tr>";
  }
  ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
                </div>

<?php 
}

}

?>
