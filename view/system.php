<?php
session_start();
// hide all error
error_reporting(0);
// protect .php
$get_self = explode("/", $_SERVER['PHP_SELF']);
$self[] = $get_self[count($get_self) - 1];

if ($self[0] !== "index.php" && $self[0] !== "") {
    include_once("../core/route.php");
} else {

    if (!isMobile()) {
        $system_ma = "sidenav_active";
        include_once("view/header_html.php");
        include_once("view/menu.php");
?>
        <div class="main">
            <div class="row">
                <div class="col-12">
                    <div class="card card-shadow">
                        <div class="card-header unselect">
                            <span><i class="fa fa-server"></i>&nbsp; <b>System Tools</b> &nbsp; </span>
                            <span class="card-tab card-tab-active" id="btn-tab-schedulers" onclick="switchSystemTab('schedulers')"><i class="fa fa-clock-o"></i> Scheduler</span>
                            <span class="card-tab" id="btn-tab-power" onclick="switchSystemTab('power')"><i class="fa fa-power-off"></i> Power</span>
                        </div>
                        <div class="card-body">
                            <!-- Tab Schedulers -->
                            <div id="panel-schedulers">
                                <div class="btn-group btn-container">
                                    <button class="bg-btn-group table-total" id="total-schedulers">&nbsp;</button>
                                    <button class="bg-btn-group" onclick="loadSchedulers()" title="Force reload"><i class="fa fa-refresh" id="fr-schedulers"></i></button>
                                    <input type="text" autocomplete="off" id="filter-schedulers" onkeyup="filterTable('schedulers','searchSchedulers',this.value)" placeholder="Filter" />
                                    <button class="bg-btn-group" onclick="filterTable('schedulers','filter-schedulers','')" title="Clear filter"><i class="fa fa-filter"></i></button>
                                </div>
                                <div class="card-fixed mr-t-10">
                                    <table class="table table-bordered table-hover system-schedulers">
                                        <thead>
                                            <tr>
                                                <th></th>
                                                <th>Name</th>
                                                <th>Start Date</th>
                                                <th>Start Time</th>
                                                <th>Interval</th>
                                                <th>Next Run</th>
                                                <th>Run Count</th>
                                                <th>Comment</th>
                                            </tr>
                                        </thead>
                                        <tbody id="schedulers" class="text-nowrap">
                                            <tr>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Tab Power -->
                            <div id="panel-power" class="hide">
                                <div class="row mr-t-10">
                                    <div class="col-6">
                                        <div class="card" style="padding: 20px; border: 1px solid #ff9800; border-radius: 6px;">
                                            <h3><i class="fa fa-refresh text-warning"></i> Reboot Router</h3>
                                            <p>Restart router MikroTik secara halus (soft reboot). Semua sesi hotspot aktif akan terputus sementara hingga router selesai boot ulang.</p>
                                            <button class="btn bg-warning" style="font-size: 15px; padding: 10px 20px;" onclick="confirmRouterPower('reboot')">
                                                <i class="fa fa-refresh"></i> Reboot Router Now
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="card" style="padding: 20px; border: 1px solid #f44336; border-radius: 6px;">
                                            <h3><i class="fa fa-power-off text-danger"></i> Shutdown Router</h3>
                                            <p>Mematikan router MikroTik secara aman. Pada RouterBOARD fisik, Anda harus menyalakan ulang tombol daya / power adaptor secara manual.</p>
                                            <button class="btn bg-danger" style="font-size: 15px; padding: 10px 20px;" onclick="confirmRouterPower('shutdown')">
                                                <i class="fa fa-power-off"></i> Shutdown Router Now
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer"><span id="loading"></span></div>
                    </div>
                </div>
            </div>
        </div>

    <?php
    } else if (isMobile()) {
        $system_ma = "nav_active";
        $navicon = '<i class="fa fa-server"></i>';
        include_once("view/header_html.php");
        include_once("view/menu.php");
    ?>
        <div class="main-mobile">
            <div class="row">
                <div style="margin-top:50px;margin-bottom:30px;">
                    <div class="group-icon-mobile" style="margin: auto; width:100%">
                        <i class="fa fa-server" style="font-size:60px"></i>
                        <h3>System Tools</h3>
                    </div>
                </div>
                <div class="col-12" style="margin-bottom:100px;">
                    <div class="mobile-card">
                        <div class="col-12 mr-b-10">
                            <button class="btn-mobile bg-primary" onclick="switchSystemTab('schedulers')"><i class="fa fa-clock-o"></i> Scheduler</button>
                            <button class="btn-mobile bg-danger" onclick="switchSystemTab('power')"><i class="fa fa-power-off"></i> Power</button>
                        </div>
                        <div id="panel-schedulers">
                            <div class="btn-group">
                                <button class="btn-mobile bg-btn-group table-total" id="total-schedulers">&nbsp;</button>
                                <button class="btn-mobile bg-btn-group" onclick="loadSchedulers()" title="Force reload"><i class="fa fa-refresh" id="fr-schedulers"></i></button>
                                <input type="text" autocomplete="off" id="filter-schedulers" onkeyup="filterTable('schedulers','searchSchedulers',this.value)" placeholder="Filter" />
                                <button class="btn-mobile bg-btn-group" onclick="filterTable('schedulers','filter-schedulers','')" title="Clear filter"><i class="fa fa-filter"></i></button>
                            </div>
                            <div class="card-fixed mr-t-10">
                                <hr>
                                <table class="table-p0 system-schedulers">
                                    <tbody id="schedulers" class="text-nowrap">
                                        <tr><td></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div id="panel-power" class="hide mr-t-10">
                            <div class="card mr-b-10" style="padding: 15px; border: 1px solid #ff9800;">
                                <h4><i class="fa fa-refresh text-warning"></i> Reboot Router</h4>
                                <p style="font-size: 13px;">Restart router MikroTik secara halus.</p>
                                <button class="btn-mobile bg-warning" onclick="confirmRouterPower('reboot')"><i class="fa fa-refresh"></i> Reboot Router</button>
                            </div>
                            <div class="card" style="padding: 15px; border: 1px solid #f44336;">
                                <h4><i class="fa fa-power-off text-danger"></i> Shutdown Router</h4>
                                <p style="font-size: 13px;">Mematikan router MikroTik secara aman.</p>
                                <button class="btn-mobile bg-danger" onclick="confirmRouterPower('shutdown')"><i class="fa fa-power-off"></i> Shutdown Router</button>
                            </div>
                        </div>
                        <div class="card-footer"><span id="loading"></span></div>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

    <script>
        $(document).ready(function() {
            setTimeout(function() {
                loadSchedulers();
            }, 100);

            $(".system-schedulers").fancyTable({
                inputId: "searchSchedulers",
                sortColumn: 1,
                pagination: true,
                perPage: 15,
                globalSearch: true,
            });
        });
    </script>
<?php
    include_once("view/footer_html.php");
}
?>
