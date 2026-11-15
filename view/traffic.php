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
        $traffic_ma = "sidenav_active";
        include_once("view/header_html.php");
        include_once("view/menu.php");
?>
        <div class="main">
            <div class="row">
                <div class="col-12">
                    <div class="card card-shadow">
                        <div class="card-header unselect">
                            <span><i class="fa fa-area-chart"></i>&nbsp; <b>Traffic Monitor</b> &nbsp; </span>
                        </div>
                        <div class="card-body">
                            <div class="row mr-b-10">
                                <div class="col-4">
                                    <label><b>Select Interface:</b></label>
                                    <select id="traffic_iface_select" class="form-control pointer" style="height:35px;font-weight:bold;">
                                        <option value="">Loading interfaces...</option>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <div class="card" style="padding: 10px; text-align: center; background: rgba(0,150,136,0.1); border-left: 4px solid #009688;">
                                        <small><i class="fa fa-arrow-down text-green"></i> <b>RX RATE</b></small>
                                        <h2 id="live_rx_rate" style="margin:5px 0 0 0; color:#009688;">0 bps</h2>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="card" style="padding: 10px; text-align: center; background: rgba(33,150,243,0.1); border-left: 4px solid #2196f3;">
                                        <small><i class="fa fa-arrow-up text-blue"></i> <b>TX RATE</b></small>
                                        <h2 id="live_tx_rate" style="margin:5px 0 0 0; color:#2196f3;">0 bps</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div id="fullTrafficChart" style="height: 420px; width: 100%;"></div>
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
        $traffic_ma = "nav_active";
        $navicon = '<i class="fa fa-area-chart"></i>';
        include_once("view/header_html.php");
        include_once("view/menu.php");
    ?>
        <div class="main-mobile">
            <div class="row">
                <div style="margin-top:50px;margin-bottom:30px;">
                    <div class="group-icon-mobile" style="margin: auto; width:100%">
                        <i class="fa fa-area-chart" style="font-size:60px"></i>
                        <h3>Traffic Monitor</h3>
                    </div>
                </div>
                <div class="col-12" style="margin-bottom:100px;">
                    <div class="mobile-card">
                        <div class="col-12 mr-b-10">
                            <label><b>Select Interface:</b></label>
                            <select id="traffic_iface_select" class="form-control" style="width:100%;height:35px;font-weight:bold;">
                                <option value="">Loading interfaces...</option>
                            </select>
                        </div>
                        <div class="row mr-b-10">
                            <div class="col-6">
                                <div class="card" style="padding: 8px; text-align: center; background: rgba(0,150,136,0.1); border-left: 3px solid #009688;">
                                    <small><i class="fa fa-arrow-down text-green"></i> <b>RX RATE</b></small>
                                    <h4 id="live_rx_rate" style="margin:5px 0 0 0; color:#009688;">0 bps</h4>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="card" style="padding: 8px; text-align: center; background: rgba(33,150,243,0.1); border-left: 3px solid #2196f3;">
                                    <small><i class="fa fa-arrow-up text-blue"></i> <b>TX RATE</b></small>
                                    <h4 id="live_tx_rate" style="margin:5px 0 0 0; color:#2196f3;">0 bps</h4>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div id="fullTrafficChart" style="height: 320px; width: 100%;"></div>
                            </div>
                        </div>
                        <div class="card-footer"><span id="loading"></span></div>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

    <script src="assets/js/highcharts/highcharts.js"></script>
    <script src="assets/js/highcharts/highcharts.theme.js"></script>
    <script>
        $(document).ready(function() {
            setTimeout(function() {
                initTrafficPage();
            }, 100);
        });
    </script>
<?php
    include_once("view/footer_html.php");
}
?>
