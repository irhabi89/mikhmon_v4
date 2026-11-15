<?php

session_start();
// hide all error
error_reporting(0);
include_once("../core/page_route.php");
include_once("../core/no_cache.php");

if (isset($_POST['sessname']) && isset($_SESSION["mikhmon"])) {
    $m_user = explode("?", $_POST['sessname'])[1];
    $action = $_POST['action'] ?? 'make_static';
    $id = $_POST['id'] ?? '';

    include_once('../core/routeros_api.class.php');
    include_once("../config/config.php");

    $iphost = explode('!', $data[$m_user][1])[1];
    $userhost = explode('@|@', $data[$m_user][2])[1];
    $passwdhost = explode('#|#', $data[$m_user][3])[1];

    $API = new RouterosAPI();
    $API->debug = false;

    if ($API->connect($iphost, $userhost, dec_rypt($passwdhost))) {
        if ($action == "make_static") {
            $res = $API->comm("/ip/dhcp-server/lease/make-static", array(
                ".id" => $id,
            ));
            echo json_encode(array("message" => "success", "result" => $res));
        } else if ($action == "remove") {
            $res = $API->comm("/ip/dhcp-server/lease/remove", array(
                ".id" => $id,
            ));
            echo json_encode(array("message" => "success", "result" => $res));
        } else {
            echo json_encode(array("message" => "invalid_action"));
        }
    } else {
        echo json_encode(array("message" => "connect_error"));
    }
} else {
    echo json_encode(array("message" => "unauthorized"));
}
