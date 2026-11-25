<?php
/* Legacy admin route table (transitional). Included in admin.php scope. */
if ($id == "login" || substr($url, -1) == "p") {

  if (isset($_POST['login'])) {
    $user = $_POST['user'];
    $pass = $_POST['pass'];
    if ($user == $useradm && $pass == decrypt($passadm)) {
      $_SESSION["mikhmon"] = $user;

        echo "<script>window.location='./admin.php?id=sessions'</script>";
    
    } else {
      $error = '<div style="width: 100%; padding:5px 0px 5px 0px; border-radius:5px;" class="bg-danger"><i class="fa fa-ban"></i> Alert!<br>Invalid username or password.</div>';
    }
  }
  

  include_once('./include/login.php');
} elseif (!isset($_SESSION["mikhmon"])) {
  echo "<script>window.location='./admin.php?id=login'</script>";
} elseif (substr($url, -1) == "/" || substr($url, -4) == ".php") {
  echo "<script>window.location='./admin.php?id=sessions'</script>";

} elseif ($id == "sessions") {
  $_SESSION["connect"] = "";
  include_once('./include/menu.php');
  include_once('./settings/sessions.php');
  /*echo '
  <script type="text/javascript">
    document.getElementById("sessname").onkeypress = function(e) {
    var chr = String.fromCharCode(e.which);
    if (" _!@#$%^&*()+=;|?,~".indexOf(chr) >= 0)
        return false;
    };
    </script>';*/
} elseif (($id == "settings" && !empty($session)) || ($id == "settings" && !empty($router))) {
  include_once('./include/menu.php');
  include_once('./settings/settings.php');
  echo '
  <script type="text/javascript">
    document.getElementById("sessname").onkeypress = function(e) {
    var chr = String.fromCharCode(e.which);
    if (" _!@#$%^&*()+=;|?,~".indexOf(chr) >= 0)
        return false;
    };
    </script>';
} elseif ($id == "connect"  && !empty($session)) {
  ini_set("max_execution_time",5);  
  include_once('./include/menu.php');
  if (empty($iphost) || empty($userhost)) {
    // Session has not been configured/saved yet (e.g. brand new router).
    $nl = '\n';
    if ($currency == in_array($currency, $cekindo['indo'])) {
      echo "<script>alert('Pengaturan router belum disimpan.".$nl."Isi IP, User dan Password lalu klik Save terlebih dahulu.')</script>";
    } else {
      echo "<script>alert('Router settings are not saved yet.".$nl."Fill in IP, User and Password then click Save first.')</script>";
    }
    echo "<script>window.location='./admin.php?id=settings&session=" . $session . "'</script>";
  } else {
    $API = new RouterosAPI();
    $API->debug = false;
    if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
      $_SESSION["connect"] = "<b class='text-green'>Connected</b>";
      echo "<script>window.location='./?session=" . $session . "'</script>";
    } else {
      $_SESSION["connect"] = "<b class='text-red'>Not Connected</b>";
      $nl = '\n';
      if ($currency == in_array($currency, $cekindo['indo'])) {
        echo "<script>alert('Mikhmon not connected!".$nl."Silakan periksa kembali IP, User, Password dan port API harus enable.".$nl."Jika menggunakan koneksi VPN, pastikan VPN tersebut terkoneksi.')</script>";
      } else {
        echo "<script>alert('Mikhmon not connected!".$nl."Please check the IP, User, Password and port API must be enabled.')</script>";
      }
      if ($c == "settings") {
        echo "<script>window.location='./admin.php?id=settings&session=" . $session . "'</script>";
      } else {
        echo "<script>window.location='./admin.php?id=sessions'</script>";
      }
    }
  }
} elseif ($id == "uplogo"  && !empty($session)) {
  include_once('./include/menu.php');
  include_once('./settings/uplogo.php');
} elseif ($id == "reboot"  && !empty($session)) {
  include_once('./process/reboot.php');
} elseif ($id == "shutdown"  && !empty($session)) {
  include_once('./process/shutdown.php');
} elseif ($id == "remove-session" && $session != "") {
  include_once('./include/menu.php');
  // Mikhmon v3.21+: remove the session from SQLite (Phase 4 of the
  // storage migration); include/config.php is only a legacy fallback.
  mikhmon_session_delete((string)$session);
  echo "<script>window.location='./admin.php?id=sessions'</script>";
} elseif ($id == "about") {
  include_once('./include/menu.php');
  include_once('./include/about.php');
} elseif ($id == "logout") {
  include_once('./include/menu.php');
  echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Logout...</b>";
  session_destroy();
  echo "<script>window.location='./admin.php?id=login'</script>";
} elseif ($id == "remove-logo" && $logo != ""  && !empty($session)) {
  include_once('./include/menu.php');
  $logopath = "./img/";
  $remlogo = $logopath . $logo;
  unlink("$remlogo");
  echo "<script>window.location='./admin.php?id=uplogo&session=" . $session . "'</script>";
} elseif ($id == "editor"  && !empty($session)) {
  include_once('./include/menu.php');
  include_once('./settings/vouchereditor.php');
} elseif (empty($id)) {
  echo "<script>window.location='./admin.php?id=sessions'</script>";
} elseif(in_array($id, $ids) && empty($session)){
	echo "<script>window.location='./admin.php?id=sessions'</script>";
}
