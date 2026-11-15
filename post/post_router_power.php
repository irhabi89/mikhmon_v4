<?php

session_start();
// hide all error
error_reporting(0);
include_once("../core/page_route.php");
include_once("../core/no_cache.php");

if (isset($_POST['sessname']) && isset($_SESSION["mikhmon"])) {
    $m_user = explode("?", $_POST['sessname'])[1];
    $action = $_POST['action'] ?? '';

    include_once('../core/routeros_api.class.php');
    include_once("../config/config.php");

    $iphost = explode('!', $data[$m_user][1])[1];
    $userhost = explode('@|@', $data[$m_user][2])[1];
    $passwdhost = explode('#|#', $data[$m_user][3])[1];

    $API = new RouterosAPI();
    $API->debug = false;

    if ($API->connect($iphost, $userhost, dec_rypt($passwdhost))) {
        if ($action == "reboot") {
            $API->write('/system/reboot');
            $API->read();
            $API->disconnect();
            echo json_encode(array("message" => "success", "action" => "reboot"));
        } else if ($action == "shutdown") {
            $API->write('/system/shutdown');
            $API->read();
            $API->disconnect();
            echo json_encode(array("message" => "success", "action" => "shutdown"));
        } else {
            echo json_encode(array("message" => "invalid_action"));
        }
    } else {
        echo json_encode(array("message" => "connect_error"));
    }
} else {
    echo json_encode(array("message" => "unauthorized"));
}
