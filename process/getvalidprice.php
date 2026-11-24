<?php
/*
 *  Copyright (C) 2019 Laksamadi Guko.
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

// load config
  include('../include/config.php');
  include('../include/readcfg.php');
  $curency = $currency;

// lang
include('../include/lang.php');
include('../lang/'.$langid.'.php');

  include_once('../lib/routeros_api.class.php');

  $API = new RouterosAPI();
  $API->debug = false;
  $API->connect($iphost, $userhost, decrypt($passwdhost));

  $uprofname = $_GET['name'];
  if ($uprofname != "") {
    $getprofile = $API->comm("/ip/hotspot/user/profile/print", array("?name" => "$uprofname"));
    $ponlogin = isset($getprofile[0]['on-login']) ? $getprofile[0]['on-login'] : '';
    $ponparts = explode(",", $ponlogin);
    $v_valid = isset($ponparts[3]) ? $ponparts[3] : '';
    $getprice = isset($ponparts[2]) ? $ponparts[2] : '0';
    $getsprice = isset($ponparts[4]) ? $ponparts[4] : '0';
    $getlock = isset($ponparts[6]) ? $ponparts[6] : '';

    $getvalid = $_validity. " : " . $v_valid;
    $lock_str = "| ".$_lock_user." : " . $getlock;
    $price = "";
    if ((float)$getprice != 0) {
      if ($curency == "Rp" || $curency == "rp" || $curency == "IDR" || $curency == "idr") {
        $price = "| ".$_price." : " . $curency . " " . number_format((float)$getprice, 0, ",", ".");
      } else {
        $price = "| ".$_price." : " . $curency . " " . number_format((float)$getprice);
      }
    }
    $sprice = "";
    if ((float)$getsprice != 0) {
      if ($curency == "Rp" || $curency == "rp" || $curency == "IDR" || $curency == "idr") {
        $sprice = "| ".$_selling_price." : " . $curency . " " . number_format((float)$getsprice, 0, ",", ".");
      } else {
        $sprice = "| ".$_selling_price." : " . $curency . " " . number_format((float)$getsprice);
      }
    }
    echo '<b id="getdata">' . $getvalid . ' ' . $price . ' ' . $sprice . ' ' . $lock_str . '</b>';
    echo '<span id="validity">' . $v_valid . '</span> ';
  }
}
?>
