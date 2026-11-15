<?php

session_start();
// hide all error
error_reporting(0);
include_once("../core/page_route.php");
include_once("../core/no_cache.php");
include_once(__DIR__ . "/../core/mikhmon_scripts.php");



if(isset($_POST['name']) && isset($_SESSION["mikhmon"])){

	$m_user = explode("?",$_POST['sessname'])[1];

    $name = (preg_replace('/\s+/', '-',$_POST['name']));
    $upid = ($_POST['upid']);
        $sharedusers = ($_POST['sharedusers']);
        $ratelimit = ($_POST['ratelimit']);
        $expmode = ($_POST['expmode']);
        $validity = ($_POST['validity']);
        $getprice = ($_POST['price']);
        $getsprice = ($_POST['sellingprice']);
        $addrpool = ($_POST['addresspool']);
        if ($getprice == "") {
          $price = "0";
        } else {
          $price = $getprice;
        }
        if ($getsprice == "") {
          $sprice = "0";
        } else {
          $sprice = $getsprice;
        }
        $getlock = ($_POST['lockuser']);
        if ($getlock == "Enable") {
          $lock = '; [:local mac $"mac-address"; /ip hotspot user set mac-address=$mac [find where name=$user]]';
        } else {
          $lock = "";
        }
        
        $srvlock = ($_POST['lockserver']);
        if ($srvlock == "Disable") {
          $slock = "";
        } else if ($srvlock !== "Disable") {
          $slock = '; [:local mac $"mac-address"; :local srv [/ip hotspot host get [find where mac-address="$mac"] server]; /ip hotspot user set server=$srv [find where name=$user]]';
        }
         
        $parent = ($_POST['parentqueue']);
        
        $mode = "";
        if ($expmode == "ntf" || $expmode == "ntfc") {
          $mode = "N";
        }else if ($expmode == "rem" || $expmode == "remc") {
          $mode = "X";
        }
        
        // Report scripts store the legacy date as source and nov2026 as owner,
        // regardless of whether the router reports dates in v6 or v7 format.
        $record = '; :local mac $"mac-address"; :local time [/system clock get time ]; :local sname "$legacy-|-$time-|-$user-|-'.$price.'-|-$address-|-$mac-|-' . $validity . '-|-'.$name.'-|-$comment"; /system script add name=$sname owner="$monthname$year" source=$legacy comment=mikhmon; /system script set [find name=$sname] owner="$monthname$year"';
        
        $onlogin = mikhmon_onlogin_script($expmode, $price, $validity, $sprice, $getlock, $srvlock, $mode, $record, $lock, $slock);

	
    // routeros api
    include_once('../core/routeros_api.class.php');

    include_once("../config/config.php");
    // read config

    $iphost = explode('!', $data[$m_user][1])[1];
    $userhost = explode('@|@', $data[$m_user][2])[1];
    $passwdhost = explode('#|#', $data[$m_user][3])[1];

    $API = new RouterosAPI();
    $API->debug = false;

    $API->connect($iphost, $userhost, dec_rypt($passwdhost));

       
	$add = $API->comm("/ip/hotspot/user/profile/set", array(
        ".id" => "$upid",	
      "name" => "$name",
      "address-pool" => "$addrpool",
      "rate-limit" => "$ratelimit",
      "shared-users" => "$sharedusers",
      "status-autorefresh" => "1m",
      "on-login" => "$onlogin",
      "parent-queue" => "$parent",
    ));

	
	
    
    if(!empty($add['!trap'][0]['message'])){
		$message = $add['!trap'][0]['message'];
		$mess = array(
			"message" => "error",
			"data" => array("error" => $message)
		  );
		echo json_encode($mess);
	}else{
    	$getuser = $API->comm("/ip/hotspot/user/profile/print", array(
	      "?.id" => "$upid",
	    ));
	 
		$mess = array(
			"message" => "success",
			"data" => $getuser[0]
		  );
		echo json_encode($mess);
	}
	

}else{
    // protect .php
$get_self = explode("/",$_SERVER['PHP_SELF']);
$self[] = $get_self[count($get_self)-1];

if($self[0] !== "index.php"  && $self[0] !==""){
  include_once("../core/page_route.php");
    include_once("../core/route.php");

 }
}
// }
   
?>
