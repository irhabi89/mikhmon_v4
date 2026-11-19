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

	$idhr = isset($_GET['idhr']) ? $_GET['idhr'] : '';
	$idbl = isset($_GET['idbl']) ? $_GET['idbl'] : '';
	$idhr_parts = explode("/", $idhr);
	$idbl2 = (count($idhr_parts) >= 3) ? ($idhr_parts[0] . $idhr_parts[2]) : '';
	if ($idhr != "") {
		$_SESSION['report'] = "&idhr=" . $idhr;
	} elseif ($idbl != "") {
		$_SESSION['report'] = "&idbl=" . $idbl;
	} else {
		$_SESSION['report'] = "";
	}
	$_SESSION['idbl'] = $idbl;
	$remdata = isset($_POST['remdata']) ? $_POST['remdata'] : null;
	$prefix = isset($_GET['prefix']) ? $_GET['prefix'] : '';
	$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
	$per_page = 50;

	include_once('./include/timezone.php');
	include_once('./include/mikhmon_records.php');
	include_once('./lib/sales_import.php');
	$gettimezone = $API->comm("/system/clock/print");
	$timezone = mikhmon_timezone_from_clock(isset($gettimezone[0]) ? $gettimezone[0] : array());
	date_default_timezone_set($timezone);

	// --- data source: the router while the import is incomplete, the DB once complete ---
	// Once an import run finishes (queue fully processed) the DB is authoritative,
	// even if the router keeps recording new sales for later imports.
	$db_count = mikhmon_sales_count($session);
	$queue_total = mikhmon_import_queue_count($session);
	$queue_done = mikhmon_import_queue_count($session, 1);
	if ($queue_total > 0) {
		$use_router = ($queue_done < $queue_total);
	} else {
		$router_total = mikhmon_import_router_total($session); // -1 when unreachable
		$use_router = ($router_total >= 0) ? ($db_count < $router_total) : ($db_count == 0);
	}

	$router_rows = array();
	if ($use_router) {
		$router_rows = mikhmon_scripts_all($API);
		$stats = mikhmon_scripts_stats($router_rows);
	} else {
		$stats = mikhmon_sales_stats($session);
	}

	// --- delete data: the modal chooses the source (DB and/or router scripts) and the scope ---
	if (isset($remdata)) {
		$del_scope = (isset($_POST['del_scope']) && $_POST['del_scope'] === 'all') ? 'all' : 'view';
		$del_db = isset($_POST['del_db']) && $_POST['del_db'] == '1';
		$del_router = isset($_POST['del_router']) && $_POST['del_router'] == '1';
		$del_src = null;
		$del_own = null;
		if ($del_scope === 'view') {
			if ($idhr !== '') {
				$del_src = $idhr;
			} elseif ($idbl !== '') {
				$del_own = $idbl;
			}
		}
		set_time_limit(0);
		if ($del_db) {
			mikhmon_sales_delete($session, $del_src, $del_own);
		}
		if ($del_router) {
			mikhmon_import_delete_period($session, $del_src, $del_own);
		}
		echo "<script>window.location='./?report=selling&session=" . $session . "'</script>";
	}

	if ($prefix != "") {
		$fprefix = "-prefix-[" . $prefix . "]";
	} else {
		$fprefix = "";
	}

	// --- report rows for the current view ---
	$allRows = array();
	$filedownload = "all";
	$shf = "text";
	$shd = "none";
	$v7date = ($idhr !== '') ? date('Y-m-d', strtotime((string)$idhr)) : '';
	$sql_paged = false;

	if ($use_router) {
		if (strlen($idhr) > "0") {
			$allRows = mikhmon_scripts_by_source($router_rows, $idhr);
			if (count($allRows) == 0) {
				foreach ($router_rows as $r) {
					if (!isset($r['name'])) { continue; }
					$d = mikhmon_script_parse((string)$r['name'])[0];
					if ($d === (string)$idhr || $d === $v7date) { $allRows[] = $r; }
				}
			}
			$filedownload = $idhr;
			$shf = "hidden";
			$shd = "inline-block";
		} elseif (strlen($idbl) > "0") {
			$allRows = mikhmon_scripts_by_month($router_rows, $idbl);
			$filedownload = $idbl;
			$shf = "hidden";
			$shd = "inline-block";
		} else {
			$allRows = $router_rows;
		}
	} else {
		if (strlen($idhr) > "0") {
			$allRows = mikhmon_sales_find($session, $idhr);
			if (count($allRows) == 0) {
				foreach (mikhmon_sales_find($session) as $r) {
					if (!isset($r['name'])) { continue; }
					$d = mikhmon_script_parse((string)$r['name'])[0];
					if ($d === (string)$idhr || $d === $v7date) { $allRows[] = $r; }
				}
			}
			$filedownload = $idhr;
			$shf = "hidden";
			$shd = "inline-block";
		} elseif (strlen($idbl) > "0") {
			$allRows = mikhmon_sales_find($session, null, $idbl);
			$filedownload = $idbl;
			$shf = "hidden";
			$shd = "inline-block";
		} else {
			// "All" view: page in SQL so tens of thousands of rows are not loaded at once.
			$TotalReg = mikhmon_sales_count_for($session);
			$sumPrice = mikhmon_sales_sum_for($session);
			$totalPages = max(1, (int)ceil($TotalReg / $per_page));
			if ($page > $totalPages) { $page = $totalPages; }
			$getData = mikhmon_sales_find($session, null, null, null, $per_page, ($page - 1) * $per_page);
			$sql_paged = true;
			$_SESSION['dataresume'] = '';
			$_SESSION['totalresume'] = $TotalReg . '/0';
		}
	}

	if ($prefix != "") {
		$filtered = array();
		foreach ($allRows as $r) {
			if (isset($r['name']) && substr((string)mikhmon_script_parse((string)$r['name'])[2], 0, strlen($prefix)) == $prefix) {
				$filtered[] = $r;
			}
		}
		$allRows = $filtered;
	}

	if (!$sql_paged) {
		$TotalReg = count($allRows);
		$sumPrice = 0.0;
		foreach ($allRows as $r) {
			$f = mikhmon_script_parse(isset($r['name']) ? (string)$r['name'] : '');
			$sumPrice += (float)$f[3];
		}
		$totalPages = max(1, (int)ceil($TotalReg / $per_page));
		if ($page > $totalPages) { $page = $totalPages; }
		$getData = array_slice($allRows, ($page - 1) * $per_page, $per_page);
		// resume report data (only needed for month views)
		$dataresume = '';
		$totalresume = 0;
		if ($idbl !== '') {
			foreach ($allRows as $r) {
				$f = mikhmon_script_parse(isset($r['name']) ? (string)$r['name'] : '');
				$dataresume .= $f[0] . $f[3];
				$totalresume += (float)$f[3];
			}
		}
		$_SESSION['dataresume'] = $dataresume;
		$_SESSION['totalresume'] = $TotalReg . '/' . $totalresume;
	}
	$pageCount = count($getData);

	// KPI helpers
	$price_decimals = in_array($currency, $cekindo['indo']) ? 0 : 2;
	$fmt = function ($v) use ($currency, $price_decimals) {
		return $currency . ' ' . number_format((float)$v, $price_decimals, ',', '.');
	};
	$delta = $stats['month_sum'] - $stats['prev_sum'];
	$delta_pct = ($stats['prev_sum'] > 0) ? ($delta / $stats['prev_sum'] * 100) : ($stats['month_sum'] > 0 ? 100 : 0);
	$page_query = 'report=selling&session=' . urlencode($session);
	if ($idhr !== '') { $page_query .= '&idhr=' . urlencode($idhr); }
	if ($idbl !== '') { $page_query .= '&idbl=' . urlencode($idbl); }
	if ($prefix !== '') { $page_query .= '&prefix=' . urlencode($prefix); }
}
?>
		<script>
			function downloadCSV(csv, filename) {
			  var csvFile;
			  var downloadLink;
			  csvFile = new Blob([csv], {type: "text/csv"});
			  downloadLink = document.createElement("a");
			  downloadLink.download = filename;
			  downloadLink.href = window.URL.createObjectURL(csvFile);
			  downloadLink.style.display = "none";
			  document.body.appendChild(downloadLink);
			  downloadLink.click();
			  }
			  function exportTableToCSV(filename) {
			    var csv = [];
			    var rows = document.querySelectorAll("#dataTable tr");
			   for (var i = 0; i < rows.length; i++) {
			      var row = [], cols = rows[i].querySelectorAll("td, th");
			   for (var j = 0; j < cols.length; j++)
            row.push(cols[j].innerText);
        csv.push(row.join(","));
        }
        downloadCSV(csv.join("\n"), filename);
        }
function number_format(number, decimals, dec_point, thousands_sep) {
  number = (number + '')
    .replace(/[^0-9+\-Ee.]/g, '');
  var n = !isFinite(+number) ? 0 : +number,
    prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
    sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
    dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
    s = '',
    toFixedFix = function (n, prec) {
      var k = Math.pow(10, prec);
      return '' + Math.round(n * k) / k;
    };
  s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
  if (s[0].length > 3) {
    s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
  }
  if ((s[1] || '').length < prec) {
    s[1] = s[1] || '';
    s[1] += new Array(prec - s[1].length + 1).join('0');
  }
  return s.join(dec);
}
// --- Import modal (chunked, pausable/resumable, persisted in the DB) ---
var impSession = <?= json_encode((string)$session); ?>;
var impRunning = false;
var impTimer = null;
function impPost(action, cb) {
  var x = new XMLHttpRequest();
  x.open('POST', './process/importsales.php', true);
  x.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  x.onreadystatechange = function () {
    if (x.readyState === 4) {
      var data;
      try { data = JSON.parse(x.responseText); } catch (e) { data = { ok: false, error: 'respons tidak valid' }; }
      cb(data);
    }
  };
  x.send('action=' + encodeURIComponent(action) + '&session=' + encodeURIComponent(impSession));
}
function impRender(s) {
  var info = document.getElementById('impInfo');
  if (!s || s.ok === false) {
    info.innerHTML = '<b class="text-danger">Gagal: ' + ((s && s.error) ? s.error : 'tidak diketahui') + '</b>';
    return;
  }
  if (typeof s.percent !== 'undefined') {
    var pct = s.percent;
    var bar = document.getElementById('impBar');
    bar.style.width = pct + '%';
    bar.innerHTML = pct + '%';
    info.innerHTML = 'Mikrotik: <b>' + (s.router_total >= 0 ? s.router_total : '?') +
      '</b> script &nbsp;|&nbsp; Database: <b>' + s.db_count +
      '</b> transaksi &nbsp;|&nbsp; Antrean: <b>' + (s.queue_done || 0) + '/' + (s.queue_total || 0) + '</b>';
  }
}
function importStatus() { impPost('status', impRender); }
function impSetButtons(start, pause, resume) {
  document.getElementById('impStartBtn').style.display = start ? '' : 'none';
  document.getElementById('impPauseBtn').style.display = pause ? '' : 'none';
  document.getElementById('impResumeBtn').style.display = resume ? '' : 'none';
}
function importStart() {
  document.getElementById('impInfo').innerHTML = 'Membaca daftar script dari Mikrotik... mohon tunggu.';
  impSetButtons(false, false, false);
  impPost('prepare', function (s) {
    impRender(s);
    if (s && s.ok) { importResume(); } else { impSetButtons(true, false, false); }
  });
}
function importResume() {
  if (impRunning) { return; }
  impRunning = true;
  impSetButtons(false, true, false);
  impChunk();
}
function importPause() {
  impRunning = false;
  if (impTimer) { clearTimeout(impTimer); impTimer = null; }
  impSetButtons(false, false, true);
}
function impChunk() {
  if (!impRunning) { return; }
  impPost('chunk', function (s) {
    impRender(s);
    if (!s || !s.ok) { impRunning = false; impSetButtons(false, false, true); return; }
    if (s.queue_total > 0 && s.queue_done >= s.queue_total) {
      if (document.getElementById('impDelete').checked) {
        impRunning = false;
        document.getElementById('impInfo').innerHTML = 'Import selesai. Menghapus script di Mikrotik...';
        impDeleteLoop();
        return;
      }
      impRunning = false;
      impSetButtons(false, false, false);
      document.getElementById('impInfo').innerHTML = '<b class="text-green">Import selesai. Memuat ulang laporan...</b>';
      setTimeout(function () { location.href = './?report=selling&session=' + encodeURIComponent(impSession); }, 800);
      return;
    }
    impTimer = setTimeout(impChunk, 120);
  });
}
function impDeleteLoop() {
  impPost('delete', function (d) {
    if (d && d.ok) {
      document.getElementById('impInfo').innerHTML = 'Menghapus script di Mikrotik... sisa <b>' + d.remaining + '</b>.';
      if (d.remaining > 0) { setTimeout(impDeleteLoop, 150); return; }
    }
    impSetButtons(false, false, false);
    setTimeout(function () { location.href = './?report=selling&session=' + encodeURIComponent(impSession); }, 800);
  });
}
window.addEventListener('hashchange', function () {
  if (window.location.hash === '#importModal') { importStatus(); }
});
</script>

<!-- KPI cards (dashboard styling) -->
<div class="row">
	<div class="col-12">
		<div class="row">
			<div class="col-3">
				<div class="box bmh-75 box-bordered">
					<div class="box-group">
						<div class="box-group-icon bg-primary"><i class="fa fa-money"></i></div>
						<div class="box-group-area">
							<b>Bulan Ini</b><br>
							<h3><?= $fmt($stats['month_sum']); ?></h3>
							<small class="<?= $delta >= 0 ? 'text-green' : 'text-danger'; ?>">
								<i class="fa fa-arrow-<?= $delta >= 0 ? 'up' : 'down'; ?>"></i>
								<?= number_format(abs($delta_pct), 1, ',', '.'); ?>%
								(<?= ($delta >= 0 ? '+' : '-') . $fmt(abs($delta)); ?>) dari bulan lalu
							</small>
						</div>
					</div>
				</div>
			</div>
			<div class="col-3">
				<div class="box bmh-75 box-bordered">
					<div class="box-group">
						<div class="box-group-icon bg-green"><i class="fa fa-calendar-check-o"></i></div>
						<div class="box-group-area">
							<b>Hari Ini</b><br>
							<h3><?= $fmt($stats['today_sum']); ?></h3>
							<small><?= number_format($stats['today_count'], 0, ',', '.'); ?> voucher terjual hari ini</small>
						</div>
					</div>
				</div>
			</div>
			<div class="col-3">
				<div class="box bmh-75 box-bordered">
					<div class="box-group">
						<div class="box-group-icon bg-indigo"><i class="fa fa-ticket"></i></div>
						<div class="box-group-area">
							<b>Voucher Keseluruhan</b><br>
							<h3><?= number_format($stats['total_count'], 0, ',', '.'); ?></h3>
							<small>total voucher tercatat</small>
						</div>
					</div>
				</div>
			</div>
			<div class="col-3">
				<div class="box bmh-75 box-bordered">
					<div class="box-group">
						<div class="box-group-icon bg-blue"><i class="fa fa-database"></i></div>
						<div class="box-group-area">
							<b>Nominal Keseluruhan</b><br>
							<h3><?= $fmt($stats['total_sum']); ?></h3>
							<small>total penjualan tercatat</small>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
$(document).ready(function(){
  $("#openResume").click(function(){
    notify("Calculating data");
    window.location = "./?report=resume-report&idbl=<?= $idbl;?>&session=<?= $session;?>"
  });
});
</script>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
	<h3><i class=" fa fa-money"></i> <?= $_selling_report ?> <?= ucfirst($idhr) . ucfirst(substr($idbl,0,3).' '.substr($idbl,3,5));	if ($prefix != "") {echo " prefix [" . $prefix . "]";} ?> <small id="loader" style="display: none;" ><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></small></h3>
</div>
<div class="card-body">
<div class="row">
	<div class="row">
	<div class="col-12">
		<div style="padding-bottom: 5px; padding-top: 5px;">
		  <input id="filterTable" type="text" class="form-control" style="float:left; margin-top: 6px; max-width: 150px;" placeholder="<?= $_search ?>">&nbsp;
		  <button name="help" class="btn bg-primary" onclick="location.href='#help';" title="Help"><i class="fa fa-question"></i> <?= $_help ?></button>
		  <button class="btn bg-primary" onclick="exportTableToCSV('report-mikhmon-<?= $filedownload . $fprefix; ?>.csv')" title="Download selling report"><i class="fa fa-download"></i> CSV</button>
			<button class="btn bg-primary" onclick="location.href='./?report=selling&session=<?= $session; ?>';" title="Reload all data"><i class="fa fa-search"></i> <?= $_all ?></button>
			<button class="btn bg-primary" onclick="location.href='#importModal';" title="Import data penjualan dari Mikrotik ke database"><i class="fa fa-upload"></i> Import</button>
			<?php if(!empty($idbl)){echo '<button name="resume" id="openResume" class="btn bg-primary"title="Resume Report"><i class="fa fa-area-chart"></i> '.$_resume.'</button>';}else{
				echo '<a class="btn bg-primary" href="./?report=selling&idbl='.$idbl2.'&session='.$session.'" title="Show '.ucfirst(substr($idbl2,0,3).' '.substr($idbl2,3,5)).'"><i class="fa fa-search"></i> '.ucfirst(substr($idbl2,0,3).' '.substr($idbl2,3,5)).'</a>';}?>
		  <button name="print" class="btn bg-primary" onclick="window.open('./report/print.php?<?= explode("?report=selling&",$url)[1] ?>','_blank');" title="Print"><i class="fa fa-print"></i> <?= $_print ?></button>
		  <button style="display: <?= $shd; ?>;" name="remdata" class="btn bg-danger" onclick="location.href='#remdata';" title="Delete Data <?= $filedownload; ?>"><i class="fa fa-trash"></i> <?= $_delete_data.' '. $filedownload; ?></button>
		  <button  id="remSelected" style="display: none;" class="btn bg-red" onclick="MikhmonRemoveReportSelected()"><i class="fa fa-trash"></i> <span id="selected"></span> <?= $_selected ?></button>
		</div>
	</div>
	</div>
		<div class="input-group mr-b-10">
			<div class="input-group-1 col-box-2">
			<select style="padding:5px;" class="group-item group-item-l" title="<?= $_days ?>" id="D">
        			<?php
										$day = isset($idhr_parts[1]) ? $idhr_parts[1] : '';
										if ($day != "") {
											echo "<option value='" . $day . "'>" . $day . "</option>";
										}
										echo "<option value=''>Day</option>";
										for ($x = 1; $x <= 31; $x++) {
											if (strlen($x) == 1) {
												$x = "0" . $x;
											} else {
												$x = $x;
											}
											echo "<option value='" . $x . "'>" . $x . "</option>";
										}
										?>
    		</select>
			</div>
			<div class="input-group-2 col-box-4">
			<select style="padding:5px;" class="group-item group-item-md" title="<?= $_months ?>" id="M">
        			<?php
										$idbls = array(1 => "jan", "feb", "mar", "apr", "may", "jun", "jul", "aug", "sep", "oct", "nov", "dec");
										$idblf = array(1 => "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
										$month = isset($idhr_parts[0]) ? $idhr_parts[0] : '';
										$month1 = substr($idbl, 0, 3);

										if ($month != "") {
											$fm = array_search($month, $idbls);
											echo "<option value='" . $month . "'>" . $idblf[$fm] . "</option>";
										} elseif ($month1 != "") {
											$fm = array_search($month1, $idbls);
											echo "<option value=" . $month1 . ">" . $idblf[$fm] . "</option>";
										} else {
											echo "<option value=" . $idbls[date("n")] . ">" . $idblf[date("n")] . "</option>";
										}
										for ($x = 1; $x <= 12; $x++) {
											echo "<option value='" . $idbls[$x] . "''>" . $idblf[$x] . "</option>";
										}
										?>
    		</select>
			</div>
			<div class="input-group-2 col-box-3">
			<select style="padding:5px;" class="group-item group-item-md" title="<?= $_years ?>" id="Y">
        			<?php
										$year = isset($idhr_parts[2]) ? $idhr_parts[2] : '';
										$year1 = substr($idbl, 3, 4);

										if ($year != "") {
											echo "<option>" . $year . "</option>";
										} elseif ($year1 != "") {
											echo "<option>" . $year1 . "</option>";
										}
											echo "<option>" . date("Y") . "</option>";
										for ($Y = 2018; $Y <= date("Y"); $Y++) {
											if ($Y == date("Y")) {
											} else {
												echo "<option value='" . $Y . "''>" . $Y . "</option>";
											}
										}
										?>
    		</select>
			</div>
            <div class="input-group-2 col-box-3">
				<div style="padding:3.5px;"  class="group-item group-item-r text-center pointer" onclick="filterR(); loader();"><i class="fa fa-search"></i> Filter</div>
			</div>
			<script type="text/javascript">
				function filterR(){
					var D = document.getElementById('D').value;
					var M = document.getElementById('M').value;
					var Y = document.getElementById('Y').value;
					var X = document.getElementById('filterTable').value;

					if(D !== ""){
						window.location='./?report=selling&idhr='+M+'/'+D+'/'+Y+'&prefix='+X+'&session=<?= $session; ?>';
					}else if(D === ""){
						window.location='./?report=selling&idbl='+M+Y+'&prefix='+X+'&session=<?= $session; ?>';
					}
				}
			</script>
		</div>
		  <div class="overflow box-bordered" style="max-height: 70vh">
			<table id="dataTable" class="table table-bordered table-hover text-nowrap">
				<thead class="thead-light">
				<tr>
				  <th colspan=5 ><?= $_selling_report ?> <?= $filedownload . $fprefix; ?><b style="font-size:0;">,,,,</b></th>
				  <th style="text-align:right;"><?= $_total ?></th>
				  <th style="text-align:right;"><?= $fmt($sumPrice); ?></th>
				</tr>
				<tr>
				  <th >&#8470;</th>
					<th ><?= $_date ?></th>
					<th ><?= $_time ?></th>
					<th ><?= $_user_name ?></th>
					<th ><?= $_profile ?></th>
					<th ><?= $_comment ?></th>
					<th style="text-align:right;"> <?= $_price ?></th>
				</tr>
				</thead>
				<tbody>
				<?php
			for ($i = 0; $i < $pageCount; $i++) {
				$getname = mikhmon_script_parse(isset($getData[$i]['name']) ? (string)$getData[$i]['name'] : '');
				echo "<tr>";
				echo "<td>" . (($page - 1) * $per_page + $i + 1) . "</td>";
				echo "<td>" . $getname[0] . "</td>";
				echo "<td>" . $getname[1] . "</td>";
				echo "<td>" . $getname[2] . "</td>";
				echo "<td>" . $getname[7] . "</td>";
				echo "<td>" . $getname[8] . "</td>";
				echo "<td style='text-align:right;'>" . $getname[3] . "</td>";
				echo "</tr>";
			}
			?>
			</tbody>
			</table>
		</div>
		<div class="row" style="padding:6px 4px 0 4px;">
			<div class="col-6" style="text-align:left;">
				Halaman <?= $page ?> dari <?= $totalPages ?> &nbsp;|&nbsp;
				<?= number_format($TotalReg, 0, ',', '.'); ?> transaksi &nbsp;|&nbsp;
				Sumber data: <b><?= $use_router ? 'Mikrotik' : 'Database'; ?></b>
			</div>
			<div class="col-6" style="text-align:right;">
				<?php
				if ($page > 1) {
					echo '<a class="btn bg-primary" href="./?' . $page_query . '&page=' . ($page - 1) . '">&laquo; Prev</a>';
				}
				$startPage = max(1, $page - 2);
				$endPage = min($totalPages, $page + 2);
				for ($p = $startPage; $p <= $endPage; $p++) {
					echo '<a class="btn ' . ($p == $page ? 'bg-primary' : 'bg-grey') . '" href="./?' . $page_query . '&page=' . $p . '">' . $p . '</a>';
				}
				if ($page < $totalPages) {
					echo '<a class="btn bg-primary" href="./?' . $page_query . '&page=' . ($page + 1) . '">Next &raquo;</a>';
				}
				?>
			</div>
		</div>
</div>
</div>
</div>
</div>

<!-- Import modal -->
<div class="modal-window" id="importModal" aria-hidden="true">
  <div>
  	<header><h1><i class="fa fa-upload"></i> Import Data Penjualan</h1></header>
  	<a style="font-weight:bold;" href="#" title="Close" class="modal-close" onclick="importPause();">X</a>
	<p>
		<span id="impInfo">Memuat status...</span>
		<div style="margin:10px 0; background:#E9EBEE; border-radius:5px; overflow:hidden; height:20px;">
			<div id="impBar" style="width:0%; background:#4dbd74; height:20px; color:#fff; text-align:center; font-size:12px; line-height:20px;">0%</div>
		</div>
		<label style="font-weight:normal; display:block; margin-bottom:8px;">
			<input type="checkbox" id="impDelete"> Hapus script penjualan di Mikrotik setelah berhasil di-import (hati-hati)
		</label>
		<div>
			<button type="button" class="btn bg-primary" id="impStartBtn" onclick="importStart();">Mulai Import</button>
			<button type="button" class="btn bg-warning" id="impPauseBtn" style="display:none;" onclick="importPause();">Jeda</button>
			<button type="button" class="btn bg-primary" id="impResumeBtn" style="display:none;" onclick="importResume();">Lanjutkan</button>
		</div>
	</p>
  </div>
</div>

<!-- Modal delete data -->
<div class="modal-window" id="remdata" aria-hidden="true">
  <div>
  	<header><h1><i class="fa fa-trash"></i> Hapus Data</h1></header>
  	<a style="font-weight:bold;" href="#" title="Close" class="modal-close">X</a>
	<p style="margin-bottom:6px;">Pilih data yang ingin dihapus:</p>
	<form autocomplete="off" method="post" action="">
	<label style="display:block; font-weight:normal;"><input type="checkbox" name="del_db" value="1" checked> Hapus data di <b>database</b></label>
	<label style="display:block; font-weight:normal;"><input type="checkbox" name="del_router" value="1"> Hapus <b>script di Mikrotik</b></label>
	<hr style="border:none;border-top:1px solid #ccc;margin:8px 0;">
	<label style="display:block; font-weight:normal;"><input type="radio" name="del_scope" value="view" checked> Hanya data yang sedang tampil (<?= $filedownload; ?>)</label>
	<label style="display:block; font-weight:normal;"><input type="radio" name="del_scope" value="all"> Semua data (semua bulan/tahun)</label>
	<center style="margin-top:10px;">
	<button type="submit" name="remdata" value="1" class="btn bg-danger"><i class="fa fa-trash"></i> Hapus</button>&nbsp;
	<a class="btn bg-secondary" href="#" title="Close" class="modal-close">Batal</a>
	</center>
	</form>
  </div>
</div>
<div class="modal-window" id="help" aria-hidden="true">
  <div>
  	<header><h1><?= $_help ?></h1></header>
  	<a style="font-weight:bold;" href="#" title="Close" class="modal-close">X</a>
	<p>
			<?= $_help_report ?>
	</p>
  </div>
</div>
</div>
