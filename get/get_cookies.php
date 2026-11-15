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
    include_once("config/connection.php");

    $get_cookies = $API->comm("/ip/hotspot/cookie/print");
    if (!is_array($get_cookies)) {
        $get_cookies = array();
    }
    $json_data = json_encode($get_cookies);

    $d = new jsEncode();
    $json_enc = $d->encodeString($json_data, 25);
    echo $json_enc;
}
