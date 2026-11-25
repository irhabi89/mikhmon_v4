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


// get MikroTik system clock
  include_once('./include/timezone.php');
  $getclock = $API->comm("/system/clock/print");
  $clock = isset($getclock[0]) ? $getclock[0] : array();
  $timezone = mikhmon_timezone_from_clock($clock);
  $_SESSION['timezone'] = $timezone;
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
/*
// move hotspot log to disk *
  $getlogging = $API->comm("/system/logging/print", array("?prefix" => "->", ));
  $logging = $getlogging[0];
  if ($logging['prefix'] == "->") {
  } else {
    $API->comm("/system/logging/add", array("action" => "disk", "prefix" => "->", "topics" => "hotspot,info,debug", ));
  }

// get hotspot log
  $getlog = $API->comm("/log/print", array("?topics" => "hotspot,info,debug", ));
  $log = array_reverse($getlog);
  $THotspotLog = count($getlog);
*/
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

  // Per-server overview: DB sales + live hotspot active/user counts.
  require_once(__DIR__ . '/../include/bootstrap.php');
  mikhmon_require_schema();
  $dash_sales = new \Mikhmon\Storage\SalesRepository(mikhmon_database());
  $dashSalesStats = array();
  foreach ($dash_sales->serverStats($session) as $row) {
    $dashSalesStats[(string)$row['server_name']] = $row;
  }
  $dashServerMap = array();
  foreach ((array)$API->comm('/ip/hotspot/print', array('.proplist' => 'name')) as $srv) {
    $name = isset($srv['name']) ? trim((string)$srv['name']) : '';
    if ($name !== '' && !isset($dashServerMap[$name])) {
      $dashServerMap[$name] = array('name' => $name, 'active' => 0, 'users' => 0);
    }
  }
  foreach ((array)$API->comm('/ip/hotspot/active/print', array('.proplist' => 'server')) as $act) {
    $name = isset($act['server']) ? trim((string)$act['server']) : '';
    if ($name === '') { continue; }
    if (!isset($dashServerMap[$name])) { $dashServerMap[$name] = array('name' => $name, 'active' => 0, 'users' => 0); }
    $dashServerMap[$name]['active']++;
  }
  foreach ((array)$API->comm('/ip/hotspot/user/print', array('.proplist' => 'server')) as $usr) {
    $name = isset($usr['server']) ? trim((string)$usr['server']) : '';
    if ($name === '' || $name === 'all') { continue; }
    if (!isset($dashServerMap[$name])) { $dashServerMap[$name] = array('name' => $name, 'active' => 0, 'users' => 0); }
    $dashServerMap[$name]['users']++;
  }
  foreach ($dashSalesStats as $name => $row) {
    if ($name === '__none__') { continue; }
    if (!isset($dashServerMap[$name])) { $dashServerMap[$name] = array('name' => $name, 'active' => 0, 'users' => 0); }
  }
  if (isset($dashSalesStats['__none__'])) {
    $dashServerMap['__none__'] = array('name' => '__none__', 'active' => 0, 'users' => 0);
  }
  $dashServerRows = array();
  foreach ($dashServerMap as $name => $base) {
    $stats = isset($dashSalesStats[$name]) ? $dashSalesStats[$name] : null;
    $dashServerRows[] = array(
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

  if ($livereport == "disable") {
    $logh = "457px";
    $lreport = "style='display:none;'";
  } else {
    $logh = "350px";
    $lreport = "style='display:block;'";
    // Pre-calculate income from the new SQLite schema (DB-first).
    require_once(__DIR__ . '/../include/bootstrap.php');
    mikhmon_require_schema();
    $sales_home = new \Mikhmon\Storage\SalesRepository(mikhmon_database());
    $stats_home = $sales_home->stats($session);
    $indo_currencies = (isset($cekindo['indo']) && is_array($cekindo['indo']))
      ? $cekindo['indo']
      : array('RP', 'Rp', 'rp', 'IDR', 'idr', 'RP.', 'Rp.', 'rp.', 'IDR.', 'idr.');
    $price_decimals = in_array((string)(isset($currency) ? $currency : 'Rp'), $indo_currencies) ? 0 : 2;
    $_SESSION[$session.'totalHr'] = $stats_home['today_count'];
    $_SESSION[$session.'dincome'] = number_format($stats_home['today_sum'], $price_decimals, ',', '.');
    $_SESSION[$session.'totalBl'] = $stats_home['month_count'];
    $_SESSION[$session.'mincome'] = number_format($stats_home['month_sum'], $price_decimals, ',', '.');
    $_SESSION[$session.'idhr'] = isset($clock['date']) ? $clock['date'] : date('M/d/Y');
  }
/*
// get selling report
    $thisD = date("d");
    $thisM = strtolower(date("M"));
    $thisY = date("Y");

    if (strlen($thisD) == 1) {
      $thisD = "0" . $thisD;
    } else {
      $thisD = $thisD;
    }

    $idhr = $thisM . "/" . $thisD . "/" . $thisY;
    $idbl = $thisM . $thisY;

    $getSRHr = $API->comm("/system/script/print", array(
      "?source" => "$idhr",
    ));
    $TotalRHr = count($getSRHr);
    $getSRBl = $API->comm("/system/script/print", array(
      "?owner" => "$idbl",
    ));
    $TotalRBl = count($getSRBl);

    for ($i = 0; $i < $TotalRHr; $i++) {

      $tHr += explode("-|-", $getSRHr[$i]['name'])[3];

    }
    for ($i = 0; $i < $TotalRBl; $i++) {

      $tBl += explode("-|-", $getSRBl[$i]['name'])[3];
    }
  }*/
}
?>
    
<div id="reloadHome">

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
                    $_SESSION[$session.'sdate'] = $clock['date'];
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

        <div class="row">
          <div  class="col-8">
            <div id="r_2"class="row">
            <div class="card">
              <div class="card-header"><h3><i class="fa fa-wifi"></i> Hotspot</h3></div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-3 col-box-6">
                      <div class="box bg-blue bmh-75">
                        <a onclick="cancelPage()" href="./?hotspot=active&session=<?= $session; ?>">
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
                      <a onclick="cancelPage()" href="./?hotspot=users&profile=all&session=<?= $session; ?>">
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
                      <a onclick="cancelPage()" href="./?hotspot-user=add&session=<?= $session; ?>">
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
                      <a onclick="cancelPage()" href="./?hotspot-user=generate&session=<?= $session; ?>">
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
            <?php \Mikhmon\Http\View::render('components/server-cards', array(
              'rows' => $dashServerRows,
              'formatMoney' => $dash_spr_fmt,
              'session' => $session,
            )); ?>
            <div class="card">
              <div class="card-header"><h3><i class="fa fa-area-chart"></i> <?= $_traffic ?> </h3></div>

              <div class="card-body">
  
                  <?php $getinterface = $API->comm("/interface/print");
                  $iface_idx = (is_numeric($iface) && (int)$iface > 0) ? ((int)$iface - 1) : 0;
                  $interface = isset($getinterface[$iface_idx]['name']) ? $getinterface[$iface_idx]['name'] : (isset($getinterface[0]['name']) ? $getinterface[0]['name'] : ''); 
                  /*$TotalReg = count($getinterface);
                  for ($i = 0; $i < $TotalReg; $i++) {
                    echo $getinterface[$i]['name'].'<br>';
                  }*/
                  ?>
                  
                  <script type="text/javascript"> 
                    var chart;
                    var sessiondata = "<?= $session ?>";
                    var interface = "<?= $interface ?>";
                    var n = 3000;
                    function requestDatta(session,iface) {
                      $.ajax({
                        url: './traffic/traffic.php?session='+session+'&iface='+iface,
                        datatype: "json",
                        success: function(data) {
                          var midata = JSON.parse(data);
                          if( midata.length > 0 ) {
                            var TX=parseInt(midata[0].data);
                            var RX=parseInt(midata[1].data);
                            var x = (new Date()).getTime(); 
                            shift=chart.series[0].data.length > 19;
                            chart.series[0].addPoint([x, TX], true, shift);
                            chart.series[1].addPoint([x, RX], true, shift);
                          }
                        },
                        error: function(XMLHttpRequest, textStatus, errorThrown) { 
                          console.error("Status: " + textStatus + " request: " + XMLHttpRequest); console.error("Error: " + errorThrown); 
                        }       
                      });
                    }	

                    $(document).ready(function() {
                        Highcharts.setOptions({
                          global: {
                            useUTC: false
                          }
                        });

                        Highcharts.addEvent(Highcharts.Series, 'afterInit', function () {
	                        this.symbolUnicode = {
    	                    circle: '●',
                          diamond: '♦',
                          square: '■',
                          triangle: '▲',
                          'triangle-down': '▼'
                          }[this.symbol] || '●';
                        });

                          chart = new Highcharts.Chart({
                          chart: {
                          renderTo: 'trafficMonitor',
                          animation: Highcharts.svg,
                          type: 'areaspline',
                          events: {
                            load: function () {
                              setInterval(function () {
                                requestDatta(sessiondata,interface);
                              }, 8000);
                            }				
                          }
                        },
                        title: {
                          text: '<?= $_interface ?> ' + interface
                        },
                        
                        xAxis: {
                          type: 'datetime',
                          tickPixelInterval: 150,
                          maxZoom: 20 * 1000,
                        },
                        yAxis: {
                            minPadding: 0.2,
                            maxPadding: 0.2,
                            title: {
                              text: null
                            },
                            labels: {
                              formatter: function () {      
                                var bytes = this.value;                          
                                var sizes = ['bps', 'kbps', 'Mbps', 'Gbps', 'Tbps'];
                                if (bytes == 0) return '0 bps';
                                var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
                                return parseFloat((bytes / Math.pow(1024, i)).toFixed(2)) + ' ' + sizes[i];                    
                              },
                            },       
                        },
                        
                        series: [{
                          name: 'Tx',
                          data: [],
                          marker: {
                            symbol: 'circle'
                          }
                        }, {
                          name: 'Rx',
                          data: [],
                          marker: {
                            symbol: 'circle'
                          }
                        }],

                        tooltip: {
                          formatter: function () { 
                            var _0x2f7f=["\x70\x6F\x69\x6E\x74\x73","\x79","\x62\x70\x73","\x6B\x62\x70\x73","\x4D\x62\x70\x73","\x47\x62\x70\x73","\x54\x62\x70\x73","\x3C\x73\x70\x61\x6E\x20\x73\x74\x79\x6C\x65\x3D\x22\x63\x6F\x6C\x6F\x72\x3A","\x63\x6F\x6C\x6F\x72","\x73\x65\x72\x69\x65\x73","\x3B\x20\x66\x6F\x6E\x74\x2D\x73\x69\x7A\x65\x3A\x20\x31\x2E\x35\x65\x6D\x3B\x22\x3E","\x73\x79\x6D\x62\x6F\x6C\x55\x6E\x69\x63\x6F\x64\x65","\x3C\x2F\x73\x70\x61\x6E\x3E\x3C\x62\x3E","\x6E\x61\x6D\x65","\x3A\x3C\x2F\x62\x3E\x20\x30\x20\x62\x70\x73","\x70\x75\x73\x68","\x6C\x6F\x67","\x66\x6C\x6F\x6F\x72","\x3A\x3C\x2F\x62\x3E\x20","\x74\x6F\x46\x69\x78\x65\x64","\x70\x6F\x77","\x20","\x65\x61\x63\x68","\x3C\x62\x3E\x4D\x69\x6B\x68\x6D\x6F\x6E\x20\x54\x72\x61\x66\x66\x69\x63\x20\x4D\x6F\x6E\x69\x74\x6F\x72\x3C\x2F\x62\x3E\x3C\x62\x72\x20\x2F\x3E\x3C\x62\x3E\x54\x69\x6D\x65\x3A\x20\x3C\x2F\x62\x3E","\x25\x48\x3A\x25\x4D\x3A\x25\x53","\x78","\x64\x61\x74\x65\x46\x6F\x72\x6D\x61\x74","\x3C\x62\x72\x20\x2F\x3E","\x20\x3C\x62\x72\x2F\x3E\x20","\x6A\x6F\x69\x6E"];var s=[];$[_0x2f7f[22]](this[_0x2f7f[0]],function(_0x3735x2,_0x3735x3){var _0x3735x4=_0x3735x3[_0x2f7f[1]];var _0x3735x5=[_0x2f7f[2],_0x2f7f[3],_0x2f7f[4],_0x2f7f[5],_0x2f7f[6]];if(_0x3735x4== 0){s[_0x2f7f[15]](_0x2f7f[7]+ this[_0x2f7f[9]][_0x2f7f[8]]+ _0x2f7f[10]+ this[_0x2f7f[9]][_0x2f7f[11]]+ _0x2f7f[12]+ this[_0x2f7f[9]][_0x2f7f[13]]+ _0x2f7f[14])};var _0x3735x2=parseInt(Math[_0x2f7f[17]](Math[_0x2f7f[16]](_0x3735x4)/ Math[_0x2f7f[16]](1024)));s[_0x2f7f[15]](_0x2f7f[7]+ this[_0x2f7f[9]][_0x2f7f[8]]+ _0x2f7f[10]+ this[_0x2f7f[9]][_0x2f7f[11]]+ _0x2f7f[12]+ this[_0x2f7f[9]][_0x2f7f[13]]+ _0x2f7f[18]+ parseFloat((_0x3735x4/ Math[_0x2f7f[20]](1024,_0x3735x2))[_0x2f7f[19]](2))+ _0x2f7f[21]+ _0x3735x5[_0x3735x2])});return _0x2f7f[23]+ Highcharts[_0x2f7f[26]](_0x2f7f[24], new Date(this[_0x2f7f[25]]))+ _0x2f7f[27]+ s[_0x2f7f[29]](_0x2f7f[28])
                          },
                          shared: true                                                      
                        },
                      });
                    });
                  </script>
                  <div id="trafficMonitor"></div>
                </div> 
              </div>
            </div>  
            <div class="col-4">
            <div id="r_4" class="row">
              <div <?= $lreport; ?> class="box bmh-75 box-bordered">
                <div class="box-group">
                  <div class="box-group-icon"><i class="fa fa-money"></i></div>
                    <div class="box-group-area">
                      <span >
                        <div id="reloadLreport">
                          <?php 
                          if (isset($_SESSION[$session.'dincome'])){
                            $total_hr = isset($_SESSION[$session.'totalHr']) ? number_format((int)$_SESSION[$session.'totalHr'], 0, ',', '.') : '0';
                            $total_bl = isset($_SESSION[$session.'totalBl']) ? number_format((int)$_SESSION[$session.'totalBl'], 0, ',', '.') : '0';
                            $dincome = $_SESSION[$session.'dincome'];
                            $mincome = $_SESSION[$session.'mincome'];
                            echo '<a href="./?report=selling&session=' . $session . '" style="color:inherit; text-decoration:none;" title="Lihat Laporan Penjualan">';
                            echo '<b>' . $_income . '</b><br/>';
                            echo $_today . ' : <b>' . $currency . ' ' . $dincome . '</b> <small style="color:#555;">(' . $total_hr . ' voucher)</small><br/>';
                            echo $_this_month . ' : <b>' . $currency . ' ' . $mincome . '</b> <small style="color:#555;">(' . $total_bl . ' voucher)</small>';
                            echo '</a>';
                          }else{
                            echo "<div id='loader' ><i><span> <i class='fa fa-circle-o-notch fa-spin'></i> ". $_processing." </i></div>";
                          }
                          ?>                       
                        </div>
                    </span>
                </div>
              </div>
            </div>
            </div>
            <div id="r_3" class="row">
            <div class="card">
              <div class="card-header">
                <h3><a onclick="cancelPage()" href="./?hotspot=log&session=<?= $session; ?>" title="Open Hotspot Log" ><i class="fa fa-align-justify"></i> <?= $_hotspot_log ?></a></h3></div>
                  <div class="card-body">
                    <div style="padding: 5px; height: <?= $logh; ?> ;" class="mr-t-10 overflow">
                      <table class="table table-sm table-bordered table-hover" style="font-size: 12px; td.padding:2px;">
                        <thead>
                          <tr>
                            <th><?= $_time ?></th>
                            <th><?= $_users ?> (IP)</th>
                            <th><?= $_messages ?></th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td colspan="3" class="text-center">
                            <div id="loader" ><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></div>
                            </td>
                          </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
              </div>
            </div>
</div>
</div>
