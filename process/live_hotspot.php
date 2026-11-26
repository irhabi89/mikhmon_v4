<?php
/**
 * Read-only NDJSON lists for hotspot tables. No DB writes, no sync jobs.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
error_reporting(0);
header('Content-Type: application/x-ndjson; charset=utf-8');
header('Cache-Control: no-store');
header('X-Accel-Buffering: no');
@ini_set('zlib.output_compression', '0');
@set_time_limit(0);
while (ob_get_level() > 0) { @ob_end_flush(); }
@ob_implicit_flush(true);
if (!isset($_SESSION['mikhmon'])) {
    echo json_encode(array('type' => 'error', 'error' => 'unauthorized')) . "\n"; flush(); exit;
}
require_once(__DIR__ . '/../include/bootstrap.php');
require_once(__DIR__ . '/../lib/router.php');
require_once(__DIR__ . '/../lib/formatbytesbites.php');

$type = isset($_GET['type']) ? (string)$_GET['type'] : '';
$session = isset($_GET['session']) ? (string)$_GET['session'] : '';
$hostMode = isset($_GET['hotspot']) ? (string)$_GET['hotspot'] : '';
$server = isset($_GET['server']) ? (string)$_GET['server'] : '';
$commands = array(
    'hosts' => array('/ip/hotspot/host/print', '.id,mac-address,address,to-address,server,comment,authorized,dynamic,DHCP,bypassed'),
    'cookies' => array('/ip/hotspot/cookie/print', '.id,user,mac-address,domain,expires-in'),
    'ipbinding' => array('/ip/hotspot/ip-binding/print', '.id,comment,mac-address,address,to-address,server,bypassed,disabled'),
);
if ($session === '' || !isset($commands[$type])) {
    echo json_encode(array('type' => 'error', 'error' => 'parameter tidak valid')) . "\n"; flush(); exit;
}
$command = $commands[$type][0];
$fields = $commands[$type][1];
$count = 0;
try {
    $client = (new \Mikhmon\Router\RouterClientFactory(mikhmon_database()))->forSession($session);
    $client->connect();
    echo json_encode(array('type' => 'meta', 'source' => 'router', 'type' => $type)) . "\n"; flush();
    $result = $client->stream($command, array('.proplist' => $fields), function (array $row) use ($type, $hostMode, $server, &$count) {
        if ($type === 'hosts') {
            if ($hostMode === 'hostp' && (string)($row['bypassed'] ?? '') !== 'true') { return true; }
            if ($hostMode === 'hosta' && (string)($row['authorized'] ?? '') !== 'true') { return true; }
            if ($server !== '' && (string)($row['server'] ?? '') !== $server) { return true; }
            $status = array();
            if (($row['authorized'] ?? '') === 'true' && ($row['DHCP'] ?? '') === 'true') { $status = array('A H', 'text-success', 'A - authorized, H - DHCP'); }
            elseif (($row['authorized'] ?? '') === 'true' && ($row['dynamic'] ?? '') === 'true') { $status = array('A D', 'text-success', 'A - authorized, D - dynamic'); }
            elseif (($row['authorized'] ?? '') === 'true') { $status = array('A', 'text-success', 'A - authorized'); }
            elseif (($row['DHCP'] ?? '') === 'true') { $status = array('H', 'text-success', 'H - DHCP'); }
            elseif (($row['dynamic'] ?? '') === 'true') { $status = array('D', 'text-success', 'D - dynamic'); }
            elseif (($row['bypassed'] ?? '') === 'true') { $status = array('P', 'text-primary', 'P - bypassed'); }
            $data = array(
                'id' => (string)($row['.id'] ?? ''), 'mac' => (string)($row['mac-address'] ?? ''),
                'address' => (string)($row['address'] ?? ''), 'to_address' => (string)($row['to-address'] ?? ''),
                'server' => (string)($row['server'] ?? ''), 'comment' => (string)($row['comment'] ?? ''),
                'status_text' => $status[0] ?? '', 'status_class' => $status[1] ?? '', 'status_title' => $status[2] ?? '',
            );
        } elseif ($type === 'cookies') {
            $data = array(
                'id' => (string)($row['.id'] ?? ''), 'user' => (string)($row['user'] ?? ''),
                'mac' => (string)($row['mac-address'] ?? ''), 'domain' => (string)($row['domain'] ?? ''),
                'expires' => formatDTM((string)($row['expires-in'] ?? '')),
            );
        } else {
            if ($server !== '' && (string)($row['server'] ?? '') !== $server) { return true; }
            $data = array(
                'id' => (string)($row['.id'] ?? ''), 'mac' => (string)($row['mac-address'] ?? ''),
                'address' => (string)($row['address'] ?? ''), 'to_address' => (string)($row['to-address'] ?? ''),
                'server' => (string)($row['server'] ?? ''), 'comment' => (string)($row['comment'] ?? ''),
                'bypassed' => (string)($row['bypassed'] ?? ''), 'disabled' => (string)($row['disabled'] ?? ''),
            );
        }
        $count++;
        echo json_encode(array('type' => 'row', 'data' => $data), JSON_UNESCAPED_SLASHES) . "\n";
        if ($count % 100 === 0) { echo json_encode(array('type' => 'progress', 'rows' => $count)) . "\n"; flush(); }
        return true;
    }, 120.0);
    $client->close();
    echo json_encode(array('type' => 'done', 'rows' => $count, 'complete' => $result->ok, 'error' => $result->error)) . "\n";
} catch (Throwable $e) {
    echo json_encode(array('type' => 'error', 'error' => $e->getMessage())) . "\n";
}
flush();
