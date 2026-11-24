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

	$getsch = $API->comm("/system/scheduler/print");
	$TotalReg = count($getsch);

	$countsch = $API->comm("/system/scheduler/print", array(
		"count-only" => "",
	));

}
?>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
	<h3><i class=" fa fa-clock-o"></i> System Scheduler
<?php
if ($countsch == 0) {
	echo "0 items";
} elseif ($countsch == 1) {
	echo "1 item";
} else {
	echo "$countsch items";
}
?>
	&nbsp;&nbsp; | &nbsp;&nbsp;<i onclick="location.reload();" class="fa fa-refresh pointer" title="Reload data"></i>
	</h3>
</div>
<div class="card-body">
	<div class="w-6">
		<input id="filterTable" type="text" class="form-control" placeholder="Search..">
	</div>
	<div class="overflow box-bordered mr-t-10" style="max-height: 75vh">
		<table id="dataTable" class="table table-bordered table-hover text-nowrap">
			<thead>
				<tr>
					<th style="width: 90px;"></th>
					<th><i class="fa fa-sort"></i> Name</th>
					<th><i class="fa fa-sort"></i> Start Date</th>
					<th><i class="fa fa-sort"></i> Start Time</th>
					<th><i class="fa fa-sort"></i> Interval</th>
					<th><i class="fa fa-sort"></i> Next Run</th>
					<th><i class="fa fa-sort"></i> Run Count</th>
					<th><i class="fa fa-sort"></i> Status</th>
					<th><i class="fa fa-sort"></i> Comment</th>
				</tr>
			</thead>
			<tbody>
<?php
			for ($i = 0; $i < $TotalReg; $i++) {
				$sch = $getsch[$i];
				$id = $sch['.id'];
				$name = $sch['name'];
				$startd = $sch['start-date'];
				$startt = $sch['start-time'];
				$interval = formatInterval($sch['interval']);
				$nextrun = $sch['next-run'];
				$runcount = $sch['run-count'];
				$comment = $sch['comment'];
				$disabled = $sch['disabled'];

				echo "<tr>";
				echo "<td style='text-align:center;'>";
				echo "<i class='fa fa-trash-o text-danger pointer' title='Remove $name' onclick=\"if(confirm('Are you sure to delete scheduler ($name)?')){loadpage('./?remove-scheduler=$id&session=$session')}\"></i>";
				echo "</td>";
				echo "<td>" . $name . "</td>";
				echo "<td>" . $startd . "</td>";
				echo "<td>" . $startt . "</td>";
				echo "<td>" . $interval . "</td>";
				echo "<td>" . $nextrun . "</td>";
				echo "<td>" . $runcount . "</td>";
				if ($disabled == "true") {
					echo "<td><span class='text-warning pointer' title='Enable Scheduler $name' onclick=\"loadpage('./?enable-scheduler=$id&session=$session')\"><i class='fa fa-play'></i> Disabled</span></td>";
				} else {
					echo "<td><span class='text-success pointer' title='Disable Scheduler $name' onclick=\"loadpage('./?disable-scheduler=$id&session=$session')\"><i class='fa fa-pause'></i> Active</span></td>";
				}
				echo "<td>" . $comment . "</td>";
				echo "</tr>";
			}
?>
			</tbody>
		</table>
	</div>
</div>
<div class="card-footer">
	<small>Scheduler yang dimuat dari RouterOS</small>
</div>
</div>
</div>
</div>
