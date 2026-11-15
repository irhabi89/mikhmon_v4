<?php

session_start();
// hide all error
error_reporting(0);
include_once("../core/page_route.php");
include_once("../core/no_cache.php");

if (isset($_POST['sessname']) && isset($_SESSION["mikhmon"])) {
    $m_user = explode("?", $_POST['sessname'])[1];
    $action = $_POST['action'] ?? 'add';

    include_once('../core/routeros_api.class.php');
    include_once("../config/config.php");

    $iphost = explode('!', $data[$m_user][1])[1];
    $userhost = explode('@|@', $data[$m_user][2])[1];
    $passwdhost = explode('#|#', $data[$m_user][3])[1];

    $API = new RouterosAPI();
    $API->debug = false;

    if ($API->connect($iphost, $userhost, dec_rypt($passwdhost))) {
        if ($action == "add") {
            $mac = trim($_POST['mac'] ?? "");
            $address = trim($_POST['address'] ?? "");
            $to_address = trim($_POST['to_address'] ?? "");
            $server = trim($_POST['server'] ?? "");
            $type = trim($_POST['type'] ?? "bypassed");
            $comment = trim($_POST['comment'] ?? "");

            $param = array();
            if ($mac !== "") {
                $param["mac-address"] = $mac;
            }
            if ($address !== "") {
                $param["address"] = $address;
            }
            if ($to_address !== "") {
                $param["to-address"] = $to_address;
            }
            if ($server !== "" && $server !== "all") {
                $param["server"] = $server;
            }
            if ($type !== "") {
                $param["type"] = $type;
            }
            if ($comment !== "") {
                $param["comment"] = $comment;
            }

            $res = $API->comm("/ip/hotspot/ip-binding/add", $param);
            echo json_encode(array("message" => "success", "result" => $res));
        } else if ($action == "toggle") {
            $id = $_POST['id'] ?? "";
            $disabled = $_POST['disabled'] ?? "no";
            $res = $API->comm("/ip/hotspot/ip-binding/set", array(
                ".id" => $id,
                "disabled" => $disabled
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
