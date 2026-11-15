<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *  MIKHMON v4 - Hotspot Voucher Status Endpoint
 */
session_start();
// hide all error
error_reporting(0);

$session = $_GET['session'] ?? '';
$uname = $_GET['name'] ?? '';

if (empty($session) || empty($uname)) {
    echo "Session and username required";
    exit;
}

include_once(__DIR__ . '/../config/config.php');
include_once(__DIR__ . '/../core/routeros_api.class.php');

if (!isset($data[$session])) {
    echo "Session not found";
    exit;
}

$iphost = explode('!', $data[$session][1])[1];
$userhost = explode('@|@', $data[$session][2])[1];
$passwdhost = explode('#|#', $data[$session][3])[1];
$hotspotname = explode('%', $data[$session][4])[1] ?? 'Hotspot';
$dnsname = explode('^', $data[$session][5])[1] ?? '';
$currency = explode('&', $data[$session][6])[1] ?? '';

$API = new RouterosAPI();
$API->debug = false;

$exp = '';
$status = 'unknown';

if ($API->connect($iphost, $userhost, dec_rypt($passwdhost))) {
    $getname = $API->comm("/ip/hotspot/user/print", array("?name" => "$uname"));
    if (!empty($getname[0])) {
        $exp = $getname[0]['comment'] ?? '';
        if (substr($exp, 3, 1) == "/" && substr($exp, 6, 1) == "/") {
            // Already formatted as date
            $status = 'active';
        } else {
            $getsch = $API->comm("/system/scheduler/print", array("?name" => "$uname"));
            if (!empty($getsch[0]['next-run'])) {
                $exp = $getsch[0]['next-run'];
                $status = 'active';
            }
        }
    }
}

if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode(array(
        "session" => $session,
        "username" => $uname,
        "expiration" => $exp,
        "status" => $status
    ));
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Voucher - <?= htmlspecialchars($hotspotname . "-" . $uname); ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta http-equiv="pragma" content="no-cache" />
    <style>
        body {
            font-family: 'Helvetica', arial, sans-serif;
            font-size: 14px;
            margin: 0;
            padding: 5px;
            color: #333;
        }
        #exp {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div id="exp"><?= htmlspecialchars($exp); ?></div>
</body>
</html>
