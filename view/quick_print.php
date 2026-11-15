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
        $quickprint_ma = "sidenav_active";
        include_once("view/header_html.php");
        include_once("view/menu.php");
?>
        <div class="main">
            <div class="row">
                <div class="col-12">
                    <div class="card card-shadow">
                        <div class="card-header unselect">
                            <span><i class="fa fa-print"></i>&nbsp; <b>Quick Print</b> &nbsp; </span>
                            <span style="float: right;">
                                <button class="btn bg-primary" onclick="openAddPresetModal()"><i class="fa fa-plus"></i> Add Preset</button>
                                <button class="btn bg-btn-group" onclick="loadQuickPrint()" title="Reload"><i class="fa fa-refresh" id="fr-quickprint"></i></button>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row" id="quick-print-container">
                                <!-- Preset cards populated by JS -->
                            </div>
                        </div>
                        <div class="card-footer"><span id="loading"></span></div>
                    </div>
                </div>
            </div>
        </div>

    <?php
    } else if (isMobile()) {
        $quickprint_ma = "nav_active";
        $navicon = '<i class="fa fa-print"></i>';
        include_once("view/header_html.php");
        include_once("view/menu.php");
    ?>
        <div class="main-mobile">
            <div class="row">
                <div style="margin-top:50px;margin-bottom:30px;">
                    <div class="group-icon-mobile" style="margin: auto; width:100%">
                        <i class="fa fa-print" style="font-size:60px"></i>
                        <h3>Quick Print</h3>
                    </div>
                </div>
                <div class="col-12" style="margin-bottom:100px;">
                    <div class="mobile-card">
                        <div class="col-12 mr-b-10">
                            <button class="btn-mobile bg-primary" onclick="openAddPresetModal()"><i class="fa fa-plus"></i> Add Preset</button>
                            <button class="btn-mobile bg-btn-group" onclick="loadQuickPrint()"><i class="fa fa-refresh"></i> Refresh</button>
                        </div>
                        <div class="row" id="quick-print-container">
                            <!-- Preset cards populated by JS -->
                        </div>
                        <div class="card-footer"><span id="loading"></span></div>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

    <!-- Modal Add/Edit Quick Print Preset -->
    <div id="modal_quick_preset" class="modal unselect">
        <div class="modal-content<?= isMobile() ? '-mobile' : ''; ?>" style="max-width: 550px;">
            <span id="closeQuickPreset" class="close" onclick="$('#modal_quick_preset').hide()"><i class="fa fa-times"></i></span>
            <div class="modal-header<?= isMobile() ? '-mobile' : ''; ?>">
                <span><i class="fa fa-print"></i>&nbsp; <b id="qp_modal_title">Add Preset Package</b></span>
            </div>
            <div class="modal-body<?= isMobile() ? '-mobile' : ''; ?>">
                <input type="hidden" id="qp_id" value="" />
                <table class="table">
                    <tr>
                        <td>Package Name</td>
                        <td><input id="qp_package" placeholder="e.g. 1 Jam / 1 Hari" autocomplete="off" class="form-control" type="text" /></td>
                    </tr>
                    <tr>
                        <td>Server</td>
                        <td>
                            <select class="form-control" id="qp_server">
                                <option value="all">all</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>Profile</td>
                        <td>
                            <select class="form-control" id="qp_profile">
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>User Mode</td>
                        <td>
                            <select class="form-control" id="qp_usrmode">
                                <option value="vc" selected>Username = Password</option>
                                <option value="up">Username & Password</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>Name Length</td>
                        <td>
                            <select class="form-control" id="qp_userlength">
                                <option value="4" selected>4</option>
                                <option value="5">5</option>
                                <option value="6">6</option>
                                <option value="7">7</option>
                                <option value="8">8</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>Prefix</td>
                        <td><input id="qp_prefix" placeholder="Optional" autocomplete="off" class="form-control" type="text" /></td>
                    </tr>
                    <tr>
                        <td>Characters</td>
                        <td>
                            <select class="form-control" id="qp_char">
                                <option value="mix" selected>5ab2c34d</option>
                                <option value="lower">abcd</option>
                                <option value="upper">ABCD</option>
                                <option value="upplow">aBcD</option>
                                <option value="mix1">5AB2C34D</option>
                                <option value="mix2">5aB2c34D</option>
                                <option value="num">1234</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>Time Limit</td>
                        <td><input id="qp_timelimit" placeholder="e.g. 1h / 1d" autocomplete="off" class="form-control" type="text" /></td>
                    </tr>
                    <tr>
                        <td>Data Limit</td>
                        <td><input id="qp_datalimit" placeholder="e.g. 500M / 1G" autocomplete="off" class="form-control" type="text" /></td>
                    </tr>
                    <tr>
                        <td>Validity</td>
                        <td><input id="qp_validity" placeholder="e.g. 1d / 30d" autocomplete="off" class="form-control" type="text" /></td>
                    </tr>
                    <tr>
                        <td>Price</td>
                        <td><input id="qp_price" placeholder="0" autocomplete="off" class="form-control" type="number" /></td>
                    </tr>
                    <tr>
                        <td>Selling Price</td>
                        <td><input id="qp_sprice" placeholder="0" autocomplete="off" class="form-control" type="number" /></td>
                    </tr>
                    <tr>
                        <td>Comment</td>
                        <td><input id="qp_comment" placeholder="Optional" autocomplete="off" class="form-control" type="text" /></td>
                    </tr>
                </table>
            </div>
            <div class="modal-footer<?= isMobile() ? '-mobile' : ''; ?>">
                <button class="btn bg-primary" style="float:right;" onclick="saveQuickPreset()"><i class="fa fa-save"></i> Save Preset</button>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            setTimeout(function() {
                loadQuickPrint();
            }, 100);
        });
    </script>
<?php
    include_once("view/footer_html.php");
}
?>
