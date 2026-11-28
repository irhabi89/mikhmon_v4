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
// check url

ob_start("ob_gzhandler");


$url = $_SERVER['REQUEST_URI'];

// load session MikroTik

$session = $_GET['session'];

if (!isset($_SESSION["mikhmon"])) {
  header("Location:./admin.php?id=login");
} elseif (empty($session)) {
  echo "<script>window.location='./admin.php?id=sessions'</script>";
} else {
  $_SESSION["$session"] = $session;
  $setsession = $_SESSION["$session"];

  $_SESSION["connect"] = "";

// time zone
  include_once('./include/timezone.php');
  date_default_timezone_set(mikhmon_valid_timezone(isset($_SESSION['timezone']) ? $_SESSION['timezone'] : ''));

// lang
  include('./include/lang.php');
  include('./lang/'.$langid.'.php');

// quick bt
  include('./include/quickbt.php');

// load config
  include('./include/config.php');
  include('./include/readcfg.php');

  // New core (Composer autoload + shared UI helpers).
  require_once(__DIR__ . '/include/bootstrap.php');
  require_once(__DIR__ . '/include/ui.php');

// theme  
  include('./include/theme.php');
  include('./settings/settheme.php');
  if (empty($_SESSION['theme'])) {
    $theme = isset($theme) ? $theme : 'dark';
    $themecolor = isset($themecolor) ? $themecolor : '#3a4149';
  } else {
    $theme = $_SESSION['theme'];
    $themecolor = isset($_SESSION['themecolor']) ? $_SESSION['themecolor'] : '#3a4149';
  }

// routeros api
  include_once('./lib/routeros_api.class.php');
  include_once('./lib/router.php');
  include_once('./lib/formatbytesbites.php');

  $request_hotspot = isset($_GET['hotspot']) ? (string)$_GET['hotspot'] : '';
  $request_report = isset($_GET['report']) ? (string)$_GET['report'] : '';
  $request_id = isset($_GET['id']) ? (string)$_GET['id'] : '';

  // Pages that only read SQLite/API-free data must not wait for the router.
  $no_router_pages = in_array($request_report, array('selling', 'userlog', 'resume-report', 'export'), true)
    || in_array($request_id, array('sessions', 'settings'), true)
    || in_array($request_hotspot, array('about', 'uplogo', 'template-editor'), true);

  $router_offline = false;
  $identity = '';
  $API = new RouterosAPI();
  $API->debug = false;

  if (!$no_router_pages) {
    $connected_api = mikhmon_router_connect($session, array(
      'attempts' => 1,
      'delay' => 0,
      'timeout' => 2,
      'read_timeout' => 60,
    ));
    if ($connected_api instanceof RouterosAPI) {
      $API = $connected_api;
    } else {
      $router_offline = true;
    }
  }

  if (!$router_offline && $API->connected) {
    $getidentity = $API->comm("/system/identity/print");
    $identity = isset($getidentity[0]['name']) ? (string)$getidentity[0]['name'] : '';
  }
  if ($identity === '') {
    $identity = isset($hotspotname) && $hotspotname !== '' ? $hotspotname : (string)$session;
  }
  

// get variable
  $hotspot = $_GET['hotspot'];
  $hotspotuser = $_GET['hotspot-user'];
  $userbyname = $_GET['hotspot-user'];
  $removeuseractive = $_GET['remove-user-active'];
  $removehost = $_GET['remove-host'];
  $removecookie = $_GET['remove-cookie'];
  $removeipbinding = $_GET['remove-ip-binding'];
  $removehotspotuser = $_GET['remove-hotspot-user'];
  $removehotspotusers = $_GET['remove-hotspot-users'];
  $removeuserprofile = $_GET['remove-user-profile'];
  $resethotspotuser = $_GET['reset-hotspot-user'];
  $removehotspotuserbycomment = $_GET['remove-hotspot-user-by-comment'];
  $removeexpiredhotspotuser = $_GET['remove-hotspot-user-expired'];
  $enablehotspotuser = $_GET['enable-hotspot-user'];
  $disablehotspotuser = $_GET['disable-hotspot-user'];
  $enableipbinding = $_GET['enable-ip-binding'];
  $disableipbinding = $_GET['disable-ip-binding'];
  $userprofile = $_GET['user-profile'];
  $userprofilebyname = $_GET['user-profile'];
  $sys = $_GET['system'];
  $enablesch = $_GET['enable-scheduler'];
  $disablesch = $_GET['disable-scheduler'];
  $removesch = $_GET['remove-scheduler'];
  $macbinding = $_GET['mac'];
  $ipbinding = $_GET['addr'];
  $ppp = $_GET['ppp'];
  $secretbyname = $_GET['secret'];
  $enablesecr = $_GET['enable-pppsecret'];
  $disablesecr = $_GET['disable-pppsecret'];
  $removesecr = $_GET['remove-pppsecret'];
  $removepprofile = $_GET['remove-pprofile'];
  $removepactive = $_GET['remove-pactive'];
  $srv = $_GET['srv'];
  $prof = $_GET['profile'];
  $comm = $_GET['comment'];
  $serveractive = $_GET['server'];
  $report = $_GET['report'];
  $removereport = $_GET['remove-report'];
  $minterface = $_GET['interface'];


  $pagehotspot = array('users','hosts','ipbinding','cookies','log','dhcp-leases');
  $pageppp = array('secrets','profiles','active',);
  $pagereport = array('userlog','selling');

  include_once('./include/headhtml.php');

  include_once('./include/menu.php');

  if (!empty($router_offline)) {
    echo '<div class="bg-danger pd-10 mr-b-10 radius-3">'
      . '<b><i class="fa fa-exclamation-triangle"></i> MikroTik tidak terhubung.</b> '
      . 'Halaman tetap ditampilkan dari data lokal. Periksa IP/port, user/password, service API, dan jaringan. '
      . '<a href="./admin.php?id=settings&session=' . urlencode((string)$session) . '" style="color:#fff;text-decoration:underline;">Buka pengaturan</a>'
      . '</div>';
  }

  $disable_sci = '<script>
  document.getElementById("comment").onkeypress = function(e) {
    var chr = String.fromCharCode(e.which);
    if (" _!@#$%^&*()+=;|?,.~".indexOf(chr) >= 0)
        return false;
};
</script>';


  require __DIR__ . '/src/Http/routes/index.routes.php';

  ?>

</div>
</div>
</div>
<?php
  $hc_parts = explode('/', (string)$url);
  $hc_last = end($hc_parts);
  $need_highcharts = ($hotspot == 'dashboard' || substr((string)$hc_last, 0, 8) == '?session' || $minterface == 'traffic-monitor' || $report == 'resume-report');
?>
<?php if ($need_highcharts): ?>
<script src="./js/highcharts/highcharts.js"></script>
<script src="./js/highcharts/themes/hc.<?= $theme; ?>.js"></script>
<?php endif; ?>
<script src="./js/mikhmon-ui.<?= $theme; ?>.min.js"></script>
<script src="./js/app.js?t=<?= file_exists("./js/app.js") ? filemtime("./js/app.js") : "1"; ?>"></script>
<script src="./js/mikhmon.js?t=<?= file_exists("./js/mikhmon.js") ? filemtime("./js/mikhmon.js") : "1"; ?>"></script>

<?php
$url_parts = explode("/", $url);
$last_url_part = end($url_parts);
$reload_interval = ((int)$areload > 0 ? (int)$areload : 10) * 1000;
if ($hotspot == "dashboard" || substr($last_url_part, 0, 8) == "?session") {
  echo '<script>
    $("#r_3").load("./dashboard/aload.php?session=' . $session . '&load=logs #r_3");  
    var interval1 = "' . $reload_interval . '";
    var mmDashSections = [["r_1", "sysresource"], ["r_2", "hotspot"], ["r_3", "logs"]];
    var mmDashIndex = 0;
    var dashboard = setInterval(function() {
      if (document.hidden) { return; }
      var section = mmDashSections[mmDashIndex++ % mmDashSections.length];
      $("#" + section[0]).load("./dashboard/aload.php?session=' . $session . '&load=" + section[1] + " #" + section[0]);
    }, interval1);

';
if ($livereport == "enable" || $livereport == "") {
  echo '
    function mmIncomeIsLive() {
      return !!document.querySelector("#r_4 [data-live-income=\"1\"]");
    }
    function mmRefreshIncome() {
      if (mmIncomeIsLive()) {
        if (window.MMLiveIncome) { window.MMLiveIncome.refresh(); }
        return;
      }
      $("#r_4").load("./report/livereport.php?session=' . $session . ' #r_4");
    }
  ';
  if($_SESSION[$session.'sdate'] != $_SESSION[$session.'idhr']){
    $_SESSION[$session.'totalHr'] = "0";
    echo 'mmRefreshIncome();';
    }else if ($_SESSION[$session.'sdate'] == $_SESSION[$session.'idhr']){  
    }else{
      echo 'mmRefreshIncome();';
    }
  echo  '
    var interval2 = "' . $reload_interval . '";
    var livereport = setInterval(function() {
      mmRefreshIncome();
  }, interval2);
 ';}
  echo ' 
  function cancelPage(){
    window.stop();
    clearInterval(dashboard);';
    if ($livereport == "enable" || $livereport == "") {
    echo '
    clearInterval(livereport);';
    }
  echo '
    }
</script>';

} elseif ($hotspot == "active") {
  echo '<style>#mmActiveReloadError{display:none;margin:8px 0;}</style>
  <div id="mmActiveReloadError" class="alert bg-danger text-white"></div>
  <script>
  (function () {
    var wrap = document.getElementById("reloadHotspotActive");
    if (!wrap) { return; }
    var loading = false;
    var interval = "' . $reload_interval . '";
    function showError(message) {
      var el = document.getElementById("mmActiveReloadError");
      if (el) { el.style.display = ""; el.innerHTML = "<b><i class=\"fa fa-exclamation-triangle\"></i> Gagal memperbarui hotspot aktif.</b> " + String(message || "Data terakhir dipertahankan."); }
    }
    function reload() {
      if (loading || document.hidden) { return; }
      loading = true;
      fetch("./hotspot/hotspotactive.php" + location.search, {credentials: "same-origin", headers: {"Accept": "text/html"}})
        .then(function (response) {
          if (!response.ok) { throw new Error("HTTP " + response.status); }
          return response.text();
        })
        .then(function (html) {
          if (html.indexOf("data-active-ok=\"1\"") === -1) { throw new Error("Router tidak terhubung"); }
          wrap.innerHTML = html;
          try { sessionStorage.setItem("mm_live_active:" + location.search, html); } catch (e) {}
          var el = document.getElementById("mmActiveReloadError");
          if (el) { el.style.display = "none"; el.innerHTML = ""; }
        })
        .catch(function (err) { showError(err.message); })
        .finally(function () { loading = false; });
    }
    wrap.addEventListener("click", function (e) {
      var a = e.target instanceof Element ? e.target.closest("a[href]") : null;
      if (!a) { return; }
      var href = a.getAttribute("href") || "";
      if (href.indexOf("hotspot=active") === -1) { return; }
      e.preventDefault();
      if (window.history && window.history.pushState) {
        window.history.pushState(null, "", href);
        try {
          var cachedHtml = sessionStorage.getItem("mm_live_active:" + location.search);
          if (cachedHtml && cachedHtml.indexOf("data-active-ok=\"1\"") !== -1) { wrap.innerHTML = cachedHtml; }
        } catch (err) {}
        reload();
      } else {
        location.assign(href);
      }
    });
    window.addEventListener("popstate", function () { reload(); });
    setInterval(reload, interval);
  })();
  </script>';
} elseif ($userprofile == "add" || substr($userprofile, 0, 1) == "*" || $userprofile != "") {
  echo "<script>
  //enable disable input on ready
$(document).ready(function(){
    var exp = document.getElementById('expmode').value;
    var val = document.getElementById('validity').style;
    var vali = document.getElementById('validi');
    if (exp === 'rem' || exp === 'remc') {
      val.display= 'table-row';
      vali.type = 'text';
      $('#validi').focus();
    } else if (exp === 'ntf' || exp === 'ntfc') {
      val.display = 'table-row';
      vali.type = 'text';
      $('#validi').focus();
    } else {
      val.display = 'none';
      vali.type = 'hidden';
    }
});
</script>";

} elseif (in_array($hotspot, $pagehotspot) || in_array($ppp, $pageppp) || in_array($report, $pagereport) || $sys == "scheduler") {
echo '
<script>
$(document).ready(function(){
  makeAllSortable();
  $("#filterTable").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#dataTable tbody tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });
});

</script>
';
}
}
?>
</body>
</html>

