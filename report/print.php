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
  $session = isset($_GET['session']) ? (string)$_GET['session'] : '';

  // lang
  include_once(__DIR__ . '/../include/lang.php');
  if (isset($langid) && file_exists(__DIR__ . '/../lang/' . $langid . '.php')) {
    include_once(__DIR__ . '/../lang/' . $langid . '.php');
  }

  // load config
  include_once(__DIR__ . '/../include/config.php');
  include_once(__DIR__ . '/../include/readcfg.php');
  include_once(__DIR__ . '/../include/mikhmon_records.php');
  include_once(__DIR__ . '/../lib/mikhmon_db.php');
  include_once(__DIR__ . '/../lib/sessions_store.php');
  include_once(__DIR__ . '/../include/timezone.php');

  // routeros api & formatting
  include_once(__DIR__ . '/../lib/routeros_api.class.php');
  include_once(__DIR__ . '/../lib/formatbytesbites.php');

  $timezone = !empty($_SESSION['timezone']) ? $_SESSION['timezone'] : 'Asia/Jakarta';
  if (function_exists('mikhmon_valid_timezone') && !mikhmon_valid_timezone($timezone)) {
    $timezone = 'Asia/Jakarta';
  }
  @date_default_timezone_set($timezone);

	$idhr = isset($_GET['idhr']) ? $_GET['idhr'] : '';
	$idbl = isset($_GET['idbl']) ? $_GET['idbl'] : '';
	$idhr_parts = explode("/", $idhr);
	$idbl2 = (count($idhr_parts) >= 3) ? ($idhr_parts[0] . $idhr_parts[2]) : '';
	if ($idhr != ""){
		$_SESSION['report'] = "&idhr=".$idhr;
	} elseif ($idbl != ""){
		$_SESSION['report'] = "&idbl=".$idbl;
	} else {
		$_SESSION['report'] = "";
	}
	$_SESSION['idbl'] = $idbl;
	$remdata = isset($_POST['remdata']) ? $_POST['remdata'] : null;
	$prefix = isset($_GET['prefix']) ? $_GET['prefix'] : '';
	$fcomment = isset($_GET['comment']) ? $_GET['comment'] : '';
	$range = isset($_GET['range']) ? $_GET['range'] : '';
	$trange = !empty($range) ? "[".$range."]" : "";
	
	$pcomment = substr($prefix, 0,2);
	if($pcomment == "!!"){
		$fcomment_parts = explode("!!", $prefix);
		$fcomment = isset($fcomment_parts[1]) ? $fcomment_parts[1] : '';
	}

  $db_count = mikhmon_sales_count($session);
  $API = null;
  // Only connect to router if DB has zero records or if removing data from router
  if ($db_count == 0 || isset($_POST['remdata'])) {
    $API = new RouterosAPI();
    $API->debug = false;
    if (!empty($iphost) && !empty($userhost)) {
      @$API->connect($iphost, $userhost, decrypt($passwdhost));
      if ($API->connected && empty($_SESSION['timezone'])) {
        $gettimezone = $API->comm("/system/clock/print");
        $tz = mikhmon_timezone_from_clock(isset($gettimezone[0]) ? $gettimezone[0] : array());
        if (!empty($tz)) {
          $timezone = $tz;
          @date_default_timezone_set($timezone);
        }
      }
      if ($db_count == 0 && $API->connected) {
        mikhmon_sales_sync($API, $session);
      }
    }
  }

	if (isset($remdata)) {
		// Mikhmon v3.21+: sales data lives in the DB; delete it there and
		// best-effort delete the corresponding scripts on the router.
		if (strlen($idhr) > "0") {
			mikhmon_sales_delete($session, $idhr);
		} elseif (strlen($idbl) > "0") {
			mikhmon_sales_delete($session, null, $idbl);
		}
		if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
			foreach (mikhmon_scripts_all($API) as $r) {
				if (!isset($r['name'])) {
					continue;
				}
				$matched = ($idbl != "" && mikhmon_script_month((string)$r['name']) === (string)$idbl)
					|| ($idhr != "" && mikhmon_script_parse((string)$r['name'])[0] === (string)$idhr);
				if (!$matched) {
					continue;
				}
				$ids = $API->comm("/system/script/print", array(
					"?comment" => "mikhmon",
					"?name" => $r['name'],
					".proplist" => ".id",
				));
				foreach ($ids as $idRow) {
					if (!isset($idRow['.id'])) {
						continue;
					}
					$API->write('/system/script/remove', false);
					$API->write('=.id=' . $idRow['.id']);
					$API->read();
				}
			}
		}
		echo "<script>window.location='./?report=selling&session=" . $session . "'</script>";
	}

	if ($pcomment == "!!"){
		$fprefix = "-comment-[" . $fcomment . "]";
	} else	if ($prefix != "") {
		$fprefix = "-prefix-[" . $prefix . "]";
	} else {
		$fprefix = "";
	}
	if (strlen($idhr) > "0") {
		$getData = mikhmon_sales_find($session, $idhr);
		$TotalReg = mikhmon_count_rows($getData);
		$filedownload = $idhr;
		$shf = "hidden";
		$shd = "inline-block";
	} elseif (strlen($idbl) > "0") {
		$getData = mikhmon_sales_find($session, null, $idbl);
		$TotalReg = mikhmon_count_rows($getData);
		$filedownload = $idbl;
		$shf = "hidden";
		$shd = "inline-block";
	} else {
		$getData = mikhmon_sales_find($session);
		$TotalReg = mikhmon_count_rows($getData);
		$filedownload = "all";
		$shf = "text";
		$shd = "none";
	}
	
}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>.:: MIKHMON <?= $hotspotname; ?> ::.</title>
		<meta charset="utf-8">
		<meta http-equiv="cache-control" content="private" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<!-- Tell the browser to be responsive to screen width -->
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<style>
	/*table*/
  .table {
    width: 100%;
    background-color: #FFFFFF;
    border-collapse: collapse !important;
  }
  
  .table td,
  .table th {
    padding: 5px;
  }
  
  .table td,
  th,
  a {
    color: #000;
    text-decoration: none;
  }
  
  .table-bordered th,
  .table-bordered td {
   border: 1px solid #000 !important;
  }
	@page
	{
   size: auto;
   margin-left: 7mm;
   margin-right: 3mm;
   margin-top: 9mm;
   margin-bottom: 3mm;
	}
	@media print
	{
   table { page-break-after:auto }
   tr    { page-break-inside:avoid; page-break-after:auto }
   td    { page-break-inside:avoid; page-break-after:auto }
   thead { display:table-header-group }
   tfoot { display:table-footer-group }
	}	 
	h3 {
		margin:0px;
	} 
		</style>
		
	</head>
	<body>
		<div class="wrapper">
		<script>
function number_format(number, decimals, dec_point, thousands_sep) {

  number = (number + '')
    .replace(/[^0-9+\-Ee.]/g, '');
  var n = !isFinite(+number) ? 0 : +number,
    prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
    sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
    dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
    s = '',
    toFixedFix = function(n, prec) {
      var k = Math.pow(10, prec);
      return '' + (Math.round(n * k) / k)
        .toFixed(prec);
    };
  // Fix for IE parseFloat(0.55).toFixed(0) = 0;
  s = (prec ? toFixedFix(n, prec) : '' + Math.round(n))
    .split('.');
  if (s[0].length > 3) {
    s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
  }
  if ((s[1] || '')
    .length < prec) {
    s[1] = s[1] || '';
    s[1] += new Array(prec - s[1].length + 1)
      .join('0');
  }
  return s.join(dec);
}
		window.onload=function() {
          var sum = 0;
          var dataTable = document.getElementById("selling");
          
          // use querySelector to find all second table cells
          var cells = document.querySelectorAll("td + td + td + td + td + td");
          for (var i = 0; i < cells.length; i++)
          sum+=parseFloat(cells[i].firstChild.data);
          
          var th = document.getElementById('total');
          
    <?php if ($currency == in_array($currency, $cekindo['indo'])) {
      echo 'th.innerHTML = "'.$currency.' " + number_format(th.innerHTML + (sum),"","",".") ;';
		} else {
			echo 'th.innerHTML = "'.$currency.' " + number_format(th.innerHTML + (sum),2,".",",") ;';
		} ?>
		
		var tables = document.getElementsByTagName('tbody');
    var table = tables[tables.length -1];
    var rows = table.rows;
    for(var i = 0, td; i < rows.length; i++){
        td = document.createElement('td');
        td.appendChild(document.createTextNode(i + 1));
        rows[i].insertBefore(td, rows[i].firstChild);
    }
        
    window.print();
        }
        

		</script>


		  <div class="overflow box-bordered" style="max-height: 70vh">
			<table id="dataTable" class="table table-bordered table-hover text-nowrap">
				<thead><tr><th colspan="7"><?= "<h3>".$_selling_report."</h3>". $hotspotname ?></th></tr></thead>
				<tr>
				  <th style="text-align:left;" colspan=5 ><?= $_selling_report ?> <?= $trange.$filedownload . $fprefix; ?><b style="font-size:0;">,,,</b></th>
				  <th style="text-align:right;"><?= $_total ?></th>
				  <th style="text-align:right;" id="total"></th>
				</tr>
				<tr style="text-align:left;">
				  <th >&#8470;</th>
					<th ><?= $_date ?></th>
					<th ><?= $_time ?></th>
					<th ><?= $_user_name ?></th>
					<th ><?= $_profile ?></th>
					<th ><?= $_comment ?></th>
					<th style="text-align:right;"> <?= $_price; ?></th>
				</tr>
				
				<tbody id="tbody">
				<?php
			if ($fcomment != "" || $pcomment == "!!") {

				for ($i = 0; $i < $TotalReg; $i++) {
					$getname = explode("-|-", $getData[$i]['name']);
					if (strpos($getname[8], $fcomment) !== false){
						echo "<tr>";
						echo "<td>";
						
						$tgl = $getname[0];
						echo $tgl;
						echo "</td>";
						echo "<td>";
						$ltime = $getname[1];
						echo $ltime;
						echo "</td>";
						echo "<td>";
						$username = $getname[2];
						echo $username;
						echo "</td>";
						echo "<td>";
						$profile = $getname[7];
						echo $profile;
						echo "</td>";
						echo "<td>";
						$comment = $getname[8];
						echo $comment;
						echo "</td>";
						echo "<td style='text-align:right;'>";
						$price = $getname[3];
						echo $price;
						echo "</td>";
						echo "</tr>";
					}
				}
			} elseif ($prefix != "") {
				for ($i = 0; $i < $TotalReg; $i++) {
					$getname = explode("-|-", $getData[$i]['name']);
					if (substr($getname[2], 0, strlen($prefix)) == $prefix) {
						echo "<tr>";
						echo "<td>";
						
						$tgl = $getname[0];
						echo $tgl;
						echo "</td>";
						echo "<td>";
						$ltime = $getname[1];
						echo $ltime;
						echo "</td>";
						echo "<td>";
						$username = $getname[2];
						echo $username;
						echo "</td>";
						echo "<td>";
						$profile = $getname[7];
						echo $profile;
						echo "</td>";
						echo "<td>";
						$comment = $getname[8];
						echo $comment;
						echo "</td>";
						echo "<td style='text-align:right;'>";
						$price = $getname[3];
						echo $price;
						echo "</td>";
						echo "</tr>";
					}
				}
			} elseif ($range != "") {
			  $x = explode('-',$range)[0];
			  $y = explode('-',$range)[1];

        $range = range($x, $y);
        
				for ($i = 0; $i < $TotalReg; $i++) {
					$getname = explode("-|-", $getData[$i]['name']);
					$day = substr($getname[0],4,2); if(substr($day,0,1) == "0"){$day = substr($day,-1);}else{$day=$day;}
					if (in_array($day, $range)) {
						echo "<tr>";
						echo "<td>";
						
						$tgl = $getname[0];
						echo $tgl;
						echo "</td>";
						echo "<td>";
						$ltime = $getname[1];
						echo $ltime;
						echo "</td>";
						echo "<td>";
						$username = $getname[2];
						echo $username;
						echo "</td>";
						echo "<td>";
						$profile = $getname[7];
						echo $profile;
						echo "</td>";
						echo "<td>";
						$comment = $getname[8];
						echo $comment;
						echo "</td>";
						echo "<td style='text-align:right;'>";
						$price = $getname[3];
						echo $price;
						echo "</td>";
						echo "</tr>";
					}
				}
			} else {
				for ($i = 0; $i < $TotalReg; $i++) {
					$getname = explode("-|-", $getData[$i]['name']);
					echo "<tr>";
					echo "<td>";
					
					$tgl = $getname[0];
					echo $tgl;
					echo "</td>";
					echo "<td>";
					$ltime = $getname[1];
					echo $ltime;
					echo "</td>";
					echo "<td>";
					$username = $getname[2];
					echo $username;
					echo "</td>";
					echo "<td>";
					$profile = $getname[7];
					echo $profile;
					echo "</td>";
					echo "<td>";
					$comment = $getname[8];
					echo $comment;
					echo "</td>";
					echo "<td style='text-align:right;'>";
					$price = $getname[3];
					echo $price;
					echo "</td>";
					echo "</tr>";
				
				$dataresume .= $getname[0].$getname[3];
				$totalresume += $getname[3];
				}
				// Write the session summary once, after the loop. Assigning inside
				// the loop copies the growing string on every iteration (O(n^2)).
				$_SESSION['dataresume'] = $dataresume;
				$_SESSION['totalresume'] = $TotalReg.'/'.$totalresume;
					
			}
			?>
			</tbody>
			</table>
		</div>

</div>
</div>
</div>
</body>
</html>
