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
        $voucher_ma = "sidenav_active";
        include_once("view/header_html.php");
        include_once("view/menu.php");
?>
        <div class="main">
            <div class="row">
                <div class="col-12">
                    <div class="card card-shadow">
                        <div class="card-header unselect">
                            <span><i class="fa fa-ticket"></i>&nbsp; <b>Vouchers (User by Profile)</b> &nbsp; </span>
                            <span style="float: right;">
                                <button class="btn bg-btn-group" onclick="loadVouchersGrid()" title="Reload"><i class="fa fa-refresh" id="fr-vouchers"></i></button>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row" id="vouchers-container">
                                <!-- Profile cards populated by JS -->
                            </div>
                        </div>
                        <div class="card-footer"><span id="loading"></span></div>
                    </div>
                </div>
            </div>
        </div>

    <?php
    } else if (isMobile()) {
        $voucher_ma = "nav_active";
        $navicon = '<i class="fa fa-ticket"></i>';
        include_once("view/header_html.php");
        include_once("view/menu.php");
    ?>
        <div class="main-mobile">
            <div class="row">
                <div style="margin-top:50px;margin-bottom:30px;">
                    <div class="group-icon-mobile" style="margin: auto; width:100%">
                        <i class="fa fa-ticket" style="font-size:60px"></i>
                        <h3>Vouchers</h3>
                    </div>
                </div>
                <div class="col-12" style="margin-bottom:100px;">
                    <div class="mobile-card">
                        <div class="col-12 mr-b-10">
                            <button class="btn-mobile bg-btn-group" onclick="loadVouchersGrid()"><i class="fa fa-refresh"></i> Refresh</button>
                        </div>
                        <div class="row" id="vouchers-container">
                            <!-- Profile cards populated by JS -->
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
                loadVouchersGrid();
            }, 100);
        });
    </script>
<?php
    include_once("view/footer_html.php");
}
?>
