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
// set  timezone
include_once('../include/timezone.php');
date_default_timezone_set(mikhmon_valid_timezone(isset($_SESSION['timezone']) ? $_SESSION['timezone'] : ''));

// lang
include('../include/lang.php');
include('../lang/'.$langid.'.php');


// load config
  include('../include/config.php');
  include('../include/readcfg.php');
  include_once('../include/mikhmon_records.php');
  include_once('../lib/sales_import.php');

// routeros api
  include_once('../lib/routeros_api.class.php');
  include_once('../lib/formatbytesbites.php');
  $API = new RouterosAPI();
  $API->debug = false;
  $API->connect($iphost, $userhost, decrypt($passwdhost));

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

    // Same data source rule as the selling report: the DB once the import
    // run finished, the router while it is still incomplete.
    $queue_total = mikhmon_import_queue_count($session);
    $queue_done = mikhmon_import_queue_count($session, 1);
    if ($queue_total > 0) {
      $use_router = ($queue_done < $queue_total);
    } else {
      $use_router = (mikhmon_sales_count($session) == 0);
    }
    if ($use_router) {
      $stats_live = mikhmon_scripts_stats(mikhmon_scripts_all($API));
    } else {
      $stats_live = mikhmon_sales_stats($session);
    }
    $tHr = $stats_live['today_sum'];
    $TotalRHr = $stats_live['today_count'];
    $tBl = $stats_live['month_sum'];
    $TotalRBl = $stats_live['month_count'];

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
                          if ($currency == in_array($currency, $cekindo['indo'])) {
                            $dincome = number_format((float)$tHr, 0, ",", ".");
                            $mincome = number_format((float)$tBl, 0, ",", ".");
                            $_SESSION[$session.'dincome'] = $dincome;
                            $_SESSION[$session.'mincome'] = $mincome;
                          }else{
                            $dincome = number_format((float)$tHr, 2);
                            $mincome = number_format((float)$tBl, 2);
                            $_SESSION[$session.'dincome'] = $dincome;
                            $_SESSION[$session.'mincome'] = $mincome;
                          }
                            echo $_income."<br/>" . "
                          ".$_today." " . $TotalRHr . "vcr : " . $currency . " " . $dincome . "<br/>
                          ".$_this_month." " . $TotalRBl . "vcr : " . $currency . " " . $mincome;
                          ?>
                        </div>
                    </span>
                </div>
              </div>
            </div>
            </div>
            