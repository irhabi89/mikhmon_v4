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

    $get_schedulers = $API->comm("/system/scheduler/print");
    if (!is_array($get_schedulers)) {
        $get_schedulers = array();
    }
    $json_data = json_encode($get_schedulers);

    $d = new jsEncode();
    $json_enc = $d->encodeString($json_data, 25);
    echo $json_enc;
}
