<?php
/* Legacy index route table (transitional). Included in index.php scope. */
// logout
  if ($hotspot == "logout") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Logout...</b>";

    session_destroy();
    echo "<script>sessionStorage.clear();</script>";
    echo "<script>window.location='./admin.php?id=login'</script>";
  }
// redirect to home
  elseif (substr(explode("=", $url)[0],-9) == "/?session") {

    include_once('./dashboard/home.php');
    $_SESSION['ubn'] = "";
  }

// redirect to home
  elseif ($hotspot == "dashboard") {
    include_once('./dashboard/home.php');
    $_SESSION['ubn'] = "";

  }

// hotspot log
  elseif ($hotspot == "log") {
    include_once('./hotspot/log.php');
  }

// hotspot log
  elseif ($report == "userlog") {
    include_once('./report/userlog.php');
  }

// about
  elseif ($hotspot == "about") {
    include_once('./include/about.php');
  }

// bad request
  elseif ($session === '' && substr($url, -1) == "=") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Bad request! redirect to Home......</b>";

    echo "<script>window.location='./'</script>";
  }

// hotspot add users
  elseif ($hotspot == "add-user") {
    $_SESSION['hua'] = "";
    include_once('./hotspot/adduser.php');
  }

// hotspot users
  elseif ($hotspot == "users" && $prof == "all") {
    $_SESSION['ubp'] = "";
    $_SESSION['hua'] = "";
    $_SESSION['ubc'] = "";
    $_SESSION['vcr'] = "";
    include_once('./hotspot/users.php');
  }

// hotspot users filter by profile
  elseif ($hotspot == "users" && $prof != "") {
    $_SESSION['ubp'] = $prof;
    $_SESSION['hua'] = "";
    $_SESSION['ubc'] = "";
    $_SESSION['vcr'] = "";
    include_once('./hotspot/users.php');
  }

// hotspot users filter by comment
  elseif ($hotspot == "users" && $comm != "") {
    $_SESSION['ubc'] = $comm;
    $_SESSION['hua'] = "";
    $_SESSION['ubp'] = "";
    $_SESSION['vcr'] = "";
    include_once('./hotspot/users.php');
  }

// hotspot by profile
  elseif ($hotspot == "users-by-profile") {
    $_SESSION['ubp'] = "";
    $_SESSION['hua'] = "";
    $_SESSION['ubc'] = "";
    $_SESSION['vcr'] = "active";
    include_once('./hotspot/userbyprofile.php');
  }
// export hotspot users
  elseif ($hotspot == "export-users") {
    include_once('./hotspot/exportusers.php');
  }

// quick print
  elseif ($hotspot == "quick-print") {
    include_once('./hotspot/quickprint.php');
  }

// quick print
elseif ($hotspot == "list-quick-print") {
  include_once('./hotspot/listquickprint.php');
}  

// add hotspot user
  elseif ($hotspotuser == "add") {
    include_once('./hotspot/adduser.php');
    echo $disable_sci;
  }

// add hotspot user
  elseif ($hotspotuser == "generate") {
    include_once('./hotspot/generateuser.php');
    echo $disable_sci;
  }

// hotspot users filter by name
  elseif (substr($hotspotuser, 0, 1) == "*") {
    $_SESSION['ubn'] = $hotspotuser;
    $_SESSION['hua'] = "";
    include_once('./hotspot/userbyname.php');
  } elseif ($hotspotuser != "") {
    $_SESSION['ubn'] = $hotspotuser;
    include_once('./hotspot/userbyname.php');
  }

// remove hotspot user
  elseif ($removehotspotuser != "" || $removehotspotusers != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removehotspotuser.php');
  }

// remove hotspot user by comment
  elseif ($removehotspotuserbycomment != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removehotspotuserbycomment.php');
  }

// remove expired hotspot user
elseif ($removeexpiredhotspotuser != "") {
  echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

  include_once('./process/removeexpiredhotspotuser.php');
}  

// reset hotspot user
  elseif ($resethotspotuser != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/resethotspotuser.php');
  }

// enable hotspot user
  elseif ($enablehotspotuser != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/enablehotspotuser.php');
  }

// disable hotspot user
  elseif ($disablehotspotuser != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/disablehotspotuser.php');
  }

// user profile
  elseif ($hotspot == "user-profiles") {
    include_once('./hotspot/userprofile.php');
  }

// add  user profile
  elseif ($userprofile == "add") {
    include_once('./hotspot/adduserprofile.php');
  }

// User profile by name
  elseif (substr($userprofile, 0, 1) == "*") {
    include_once('./hotspot/userprofilebyname.php');
  } elseif ($userprofile != "") {
    include_once('./hotspot/userprofilebyname.php');
  }


// remove user profile
  elseif ($removeuserprofile != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removeuserprofile.php');
  }

// hotspot active
  elseif ($hotspot == "active") {
    $_SESSION['ubp'] = "";
    $_SESSION['hua'] = "hotspotactive";
    $_SESSION['ubc'] = "";
    include_once('./hotspot/hotspotactive.php');
  }

// dhcp leases
  elseif ($hotspot == "dhcp-leases") {
    include_once('./dhcp/dhcpleases.php');
  }

// traffic monitor
  elseif ($minterface == "traffic-monitor") {
  include_once('./traffic/trafficmonitor.php');
}

// hotspot hosts
  elseif ($hotspot == "hosts" || $hotspot == "hostp" || $hotspot == "hosta") {
    include_once('./hotspot/hosts.php');
  }

// hotspot bindings
  elseif ($hotspot == "binding") {
    include_once('./hotspot/binding.php');
  }

// template editor
  elseif ($hotspot == "template-editor") {
    include_once('./settings/vouchereditor.php');
  }

// upload logo
  elseif ($hotspot == "uplogo") {
    include_once('./settings/uplogo.php');
  }

// hotspot Cookies
  elseif ($hotspot == "cookies") {
    include_once('./hotspot/cookies.php');
  }

// remove hotspot Cookies
  elseif ($removecookie != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removecookie.php');
  }

// hotspot Ip Bindings
  elseif ($hotspot == "ipbinding") {
    include_once('./hotspot/ipbinding.php');
  }

// remove enable disable ipbinding
  elseif ($removeipbinding != "" || $enableipbinding != "" || $disableipbinding != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/pipbinding.php');
  }


// remove user active
  elseif ($removeuseractive != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removeuseractive.php');
  }

// remove host
  elseif ($removehost != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removehost.php');
  }


// makebinding
  elseif ($macbinding != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/makebinding.php');
  }

// selling
  elseif ($report == "selling") {
    include_once('./report/selling.php');
  }

// selling
elseif ($report == "resume-report") {
  include_once('./report/resumereport.php');
}

// selling
elseif ($report == "export") {
  include_once('./report/export.php');
}

// selling
  elseif ($removereport != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removereport.php');
  }

// ppp secret
  elseif ($ppp == "secrets") {
    include_once('./ppp/pppsecrets.php');
  }

// ppp addsecret
  elseif ($ppp == "addsecret") {
    include_once('./ppp/addsecret.php');
  }

// ppp secretbyname
  elseif ($secretbyname != "") {
    include_once('./ppp/secretbyname.php');
  }

// remove enable disable secret
  elseif ($removesecr != "" || $enablesecr != "" || $disablesecr != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/psecret.php');
  }


// ppp profile
  elseif ($ppp == "profiles") {
    include_once('./ppp/pppprofile.php');
  }

// add ppp profile
  elseif ($ppp == "add-profile") {
    include_once('./ppp/addpppprofile.php');
  }

// add ppp profile
elseif ($ppp == "edit-profile") {
  include_once('./ppp/profilebyname.php');
}
// remove enable disable profile
  elseif ($removepprofile != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removepprofile.php');
  }

// ppp active connection
  elseif ($ppp == "active") {
    include_once('./ppp/pppactive.php');
  }

// remove ppp active connection
  elseif ($removepactive != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removepactive.php');
  }

// sys scheduler
  elseif ($sys == "scheduler") {
    include_once('./system/scheduler.php');
  }
// remove enable disable scheduler
  elseif ($removesch != "" || $enablesch != "" || $disablesch != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/pscheduler.php');
  }

