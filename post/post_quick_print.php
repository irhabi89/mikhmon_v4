<?php

session_start();
// hide all error
error_reporting(0);
include_once("../core/page_route.php");
include_once("../core/no_cache.php");
include_once("../core/generator_functions.php");

if (isset($_POST['sessname']) && isset($_SESSION["mikhmon"])) {
    $m_user = explode("?", $_POST['sessname'])[1];
    $action = $_POST['action'] ?? 'save_preset';

    include_once('../core/routeros_api.class.php');
    include_once("../config/config.php");

    $iphost = explode('!', $data[$m_user][1])[1];
    $userhost = explode('@|@', $data[$m_user][2])[1];
    $passwdhost = explode('#|#', $data[$m_user][3])[1];

    $API = new RouterosAPI();
    $API->debug = false;

    if ($API->connect($iphost, $userhost, dec_rypt($passwdhost))) {
        if ($action == "save_preset") {
            $id = trim($_POST['id'] ?? "");
            $package = trim($_POST['package'] ?? "Preset");
            $sname = "Quick_Print_" . preg_replace('/\s+/', '-', $package);
            $server = trim($_POST['server'] ?? "all");
            $usermode = trim($_POST['usermode'] ?? "vc");
            $userl = trim($_POST['userlength'] ?? "4");
            $prefix = trim($_POST['prefix'] ?? "");
            $char = trim($_POST['char'] ?? "lower");
            $profile = trim($_POST['profile'] ?? "default");
            $timelimit = trim($_POST['timelimit'] ?? "0");
            $datalimit = trim($_POST['datalimit'] ?? "0");
            $comment = trim($_POST['comment'] ?? "");
            $validity = trim($_POST['validity'] ?? "");
            $price = trim($_POST['price'] ?? "0");
            $sprice = trim($_POST['sprice'] ?? $price);
            $userlock = trim($_POST['userlock'] ?? "Disable");

            $source = '#' . $package . '#' . $server . '#' . $usermode . '#' . $userl . '#' . $prefix . '#' . $char . '#' . $profile . '#' . $timelimit . '#' . $datalimit . '#' . $comment . '#' . $validity . '#' . $price . '_' . $sprice . '#' . $userlock;

            if ($id !== "") {
                $res = $API->comm("/system/script/set", array(
                    ".id" => $id,
                    "name" => $sname,
                    "source" => $source,
                    "comment" => "QuickPrintMikhmon",
                ));
            } else {
                $res = $API->comm("/system/script/add", array(
                    "name" => $sname,
                    "source" => $source,
                    "comment" => "QuickPrintMikhmon",
                ));
            }

            echo json_encode(array("message" => "success", "result" => $res));
        } else if ($action == "delete_preset") {
            $id = trim($_POST['id'] ?? "");
            $res = $API->comm("/system/script/remove", array(
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
