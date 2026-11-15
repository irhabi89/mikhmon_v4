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

    $get_scripts = $API->comm("/system/script/print", array("?comment" => "QuickPrintMikhmon"));
    if (!is_array($get_scripts)) {
        $get_scripts = array();
    }

    $presets = array();
    foreach ($get_scripts as $sc) {
        $src = explode("#", $sc['source']);
        $price_parts = explode("_", $src[12] ?? "");
        $presets[] = array(
            "id" => $sc['.id'],
            "name" => $sc['name'],
            "package" => $src[1] ?? "",
            "server" => $src[2] ?? "all",
            "usermode" => $src[3] ?? "up",
            "userlength" => $src[4] ?? "4",
            "prefix" => $src[5] ?? "",
            "char" => $src[6] ?? "lower",
            "profile" => $src[7] ?? "default",
            "timelimit" => $src[8] ?? "",
            "datalimit" => $src[9] ?? "",
            "comment" => $src[10] ?? "",
            "validity" => $src[11] ?? "",
            "price" => $price_parts[0] ?? "0",
            "sprice" => $price_parts[1] ?? ($price_parts[0] ?? "0"),
            "userlock" => $src[13] ?? "Disable",
        );
    }

    $json_data = json_encode($presets);
    $d = new jsEncode();
    $json_enc = $d->encodeString($json_data, 25);
    echo $json_enc;
}
