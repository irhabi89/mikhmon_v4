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
        $dhcp_ma = "sidenav_active";
        include_once("view/header_html.php");
        include_once("view/menu.php");
?>
        <div class="main">
            <div class="row">
                <div class="col-12">
                    <div class="card card-shadow">
                        <div class="card-header unselect">
                            <span><i class="fa fa-sitemap"></i>&nbsp; <b>DHCP Leases</b> &nbsp; </span>
                        </div>
                        <div class="card-body">
                            <div class="btn-group btn-container">
                                <button class="bg-btn-group table-total" id="total-dhcpleases">&nbsp;</button>
                                <button class="bg-btn-group" onclick="loadDHCPLeases()" title="Force reload"><i class="fa fa-refresh" id="fr-dhcpleases"></i></button>
                                <input type="text" autocomplete="off" id="filter-dhcpleases" onkeyup="filterTable('dhcpleases','searchDHCPLeases',this.value)" placeholder="Filter" />
                                <button class="bg-btn-group" onclick="filterTable('dhcpleases','filter-dhcpleases','')" title="Clear filter"><i class="fa fa-filter"></i></button>
                            </div>
                            <div class="btn-group btn-container">
                                <button class="bg-btn-group" onclick="filterTable('dhcpleases','searchDHCPLeases','')" title="All">All</button>
                                <button class="bg-btn-group" onclick="filterTable('dhcpleases','searchDHCPLeases','bound')" title="Filter Bound">Bound</button>
                                <button class="bg-btn-group" onclick="filterTable('dhcpleases','searchDHCPLeases','waiting')" title="Filter Waiting">Waiting</button>
                                <button class="bg-btn-group" onclick="filterTable('dhcpleases','searchDHCPLeases','dynamic')" title="Filter Dynamic">Dynamic</button>
                                <button class="bg-btn-group" onclick="filterTable('dhcpleases','searchDHCPLeases','static')" title="Filter Static">Static</button>
                            </div>
                            <div class="card-fixed mr-t-10">
                                <table class="table table-bordered table-hover dhcp-leases">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th>Type</th>
                                            <th>Address</th>
                                            <th>MAC Address</th>
                                            <th>Server</th>
                                            <th>Active Address</th>
                                            <th>Active MAC</th>
                                            <th>Host Name</th>
                                            <th>Status</th>
                                            <th>Expires After</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dhcpleases" class="text-nowrap">
                                        <tr>
                                            <td></td>
                                            <td></td>
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
                        <div class="card-footer"><span id="loading"></span></div>
                    </div>
                </div>
            </div>
        </div>

    <?php
    } else if (isMobile()) {
        $dhcp_ma = "nav_active";
        $navicon = '<i class="fa fa-sitemap"></i>';
        include_once("view/header_html.php");
        include_once("view/menu.php");
    ?>
        <div class="main-mobile">
            <div class="row">
                <div style="margin-top:50px;margin-bottom:30px;">
                    <div class="group-icon-mobile" style="margin: auto; width:100%">
                        <i class="fa fa-sitemap" style="font-size:60px"></i>
                        <h3>DHCP Leases</h3>
                    </div>
                </div>
                <div class="col-12" style="margin-bottom:100px;">
                    <div class="mobile-card">
                        <div class="col-12 mr-b-10">
                            <div class="btn-group">
                                <button class="btn-mobile bg-btn-group table-total" id="total-dhcpleases">&nbsp;</button>
                                <button class="btn-mobile bg-btn-group" onclick="loadDHCPLeases()" title="Force reload"><i class="fa fa-refresh" id="fr-dhcpleases"></i></button>
                                <input type="text" autocomplete="off" id="filter-dhcpleases" onkeyup="filterTable('dhcpleases','searchDHCPLeases',this.value)" placeholder="Filter" />
                                <button class="btn-mobile bg-btn-group" onclick="filterTable('dhcpleases','filter-dhcpleases','')" title="Clear filter"><i class="fa fa-filter"></i></button>
                            </div>
                        </div>
                        <div class="card-fixed mr-t-10">
                            <hr>
                            <table class="table-p0 dhcp-leases">
                                <tbody id="dhcpleases" class="text-nowrap">
                                    <tr><td></td></tr>
                                </tbody>
                            </table>
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
                loadDHCPLeases();
            }, 100);

            $(".dhcp-leases").fancyTable({
                inputId: "searchDHCPLeases",
                sortColumn: 2,
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
