<?php
/* Read-only scheduler status for the hotspot profile list. */
session_start();
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['mikhmon'])) {
    http_response_code(401);
    echo json_encode(array('ok' => false, 'error' => 'unauthorized'));
    exit;
}

require_once(__DIR__ . '/../include/bootstrap.php');
require_once(__DIR__ . '/../lib/router.php');
include(__DIR__ . '/../include/config.php');
include(__DIR__ . '/../include/readcfg.php');
$session = isset($_GET['session']) ? (string)$_GET['session'] : '';
session_write_close();
if ($session === '') {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'error' => 'session required'));
    exit;
}

try {
    $API = mikhmon_router_connect($session, array('attempts' => 1, 'delay' => 0, 'timeout' => 3, 'read_timeout' => 30));
    if (!$API) {
        throw new RuntimeException('router connection failed');
    }
    $profilesRead = mikhmon_router_cached($API, $session, '/ip/hotspot/user/profile/print', array('.proplist' => 'name'), 60);
    $result = array();
    foreach ((array)$profilesRead['data'] as $profileRow) {
        $pname = isset($profileRow['name']) ? (string)$profileRow['name'] : '';
        if ($pname === '') { continue; }
        $schedRead = mikhmon_router_cached($API, $session, '/system/scheduler/print', array('?name' => $pname, '.proplist' => '.id,name,disabled'), 60);
        $sched = isset($schedRead['data'][0]) && is_array($schedRead['data'][0]) ? $schedRead['data'][0] : array();
        $result[$pname] = array(
            'name' => isset($sched['name']) ? (string)$sched['name'] : '',
            'disabled' => isset($sched['disabled']) ? (string)$sched['disabled'] : '',
        );
    }
    echo json_encode(array('ok' => true, 'data' => $result, 'cached' => !empty($profilesRead['cached'])));
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(array('ok' => false, 'error' => $e->getMessage()));
}
