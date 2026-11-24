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
if (session_status() === PHP_SESSION_NONE) session_start();
// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  exit;
} else {
// load session MikroTik
  $session = $_GET['session'];
// set  timezone
include_once(__DIR__ . '/../include/timezone.php');
date_default_timezone_set(mikhmon_valid_timezone(isset($_SESSION['timezone']) ? $_SESSION['timezone'] : ''));

// lang
include(__DIR__ . '/../include/lang.php');
include(__DIR__ . '/../lang/'.$langid.'.php');


// load config
include(__DIR__ . '/../include/config.php');
include(__DIR__ . '/../include/readcfg.php');
include_once(__DIR__ . '/../include/mikhmon_records.php');
include_once(__DIR__ . '/../lib/sales_import.php');
include_once(__DIR__ . '/../lib/sessions_store.php');
include_once(__DIR__ . '/../lib/formatbytesbites.php');

  if ($livereport == "disable") {
    $logh = "457px";
    $lreport = "style='display:none;'";
  } else {
    $logh = "350px";
    $lreport = "style='display:block;'";
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

    // DB-first: read from SQLite whenever data exists in DB (<0.04s, zero WAN blocking).
    $db_count = mikhmon_sales_count($session);
    $use_router = ($db_count == 0);

    if ($use_router) {
      include_once('../lib/routeros_api.class.php');
      $API = new RouterosAPI();
      $API->debug = false;
      if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
        $stats_live = mikhmon_scripts_stats(mikhmon_scripts_all($API));
        $API->disconnect();
      } else {
        $stats_live = array('today_sum' => 0, 'today_count' => 0, 'month_sum' => 0, 'month_count' => 0);
      }
    } else {
      $stats_live = mikhmon_sales_stats($session);
    }
    $tHr = $stats_live['today_sum'];
    $TotalRHr = $stats_live['today_count'];
    $tBl = $stats_live['month_sum'];
    $TotalRBl = $stats_live['month_count'];

    $indo_currencies = (isset($cekindo['indo']) && is_array($cekindo['indo']))
      ? $cekindo['indo']
      : array('RP', 'Rp', 'rp', 'IDR', 'idr', 'RP.', 'Rp.', 'rp.', 'IDR.', 'idr.');
    $price_decimals = in_array((string)(isset($currency) ? $currency : 'Rp'), $indo_currencies) ? 0 : 2;
    $_SESSION[$session.'dincome'] = number_format($tHr, $price_decimals, ',', '.');
    $_SESSION[$session.'mincome'] = number_format($tBl, $price_decimals, ',', '.');
    $_SESSION[$session.'idhr'] = $idhr;
    $_SESSION[$session.'totalBl'] = $TotalRBl;
    $_SESSION[$session.'totalHr'] = $TotalRHr;
  }
}
?>

            <div id="r_4" class="row">
              <div <?= $lreport; ?> class="box bmh-75 box-bordered">
                <div class="box-group">
                  <div class="box-group-icon"><i class="fa fa-money"></i></div>
                    <div class="box-group-area">
                      <span >
                        <div id="reloadLreport">
                        <?php 
                          $total_hr = isset($_SESSION[$session.'totalHr']) ? number_format((int)$_SESSION[$session.'totalHr'], 0, ',', '.') : '0';
                          $total_bl = isset($_SESSION[$session.'totalBl']) ? number_format((int)$_SESSION[$session.'totalBl'], 0, ',', '.') : '0';
                          $dincome = isset($_SESSION[$session.'dincome']) ? $_SESSION[$session.'dincome'] : '0';
                          $mincome = isset($_SESSION[$session.'mincome']) ? $_SESSION[$session.'mincome'] : '0';
                          echo '<a href="./?report=selling&session=' . $session . '" style="color:inherit; text-decoration:none;" title="Lihat Laporan Penjualan">';
                          echo '<b>' . $_income . '</b><br/>';
                          echo $_today . ' : <b>' . $currency . ' ' . $dincome . '</b> <small style="color:#555;">(' . $total_hr . ' voucher)</small><br/>';
                          echo $_this_month . ' : <b>' . $currency . ' ' . $mincome . '</b> <small style="color:#555;">(' . $total_bl . ' voucher)</small>';
                          echo '</a>';
                        ?>
                        </div>
                    </span>
                </div>
              </div>
            </div>
            </div>
            