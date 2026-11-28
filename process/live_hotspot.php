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
require_once(__DIR__ . '/../include/report_fallback.php');
require_once(__DIR__ . '/../lib/router.php');
require_once(__DIR__ . '/../lib/formatbytesbites.php');

$type = isset($_GET['type']) ? (string)$_GET['type'] : '';
$session = isset($_GET['session']) ? (string)$_GET['session'] : '';
$hostMode = isset($_GET['hotspot']) ? (string)$_GET['hotspot'] : '';
$server = isset($_GET['server']) ? (string)$_GET['server'] : '';
$fresh = isset($_GET['fresh']) && (string)$_GET['fresh'] === '1';
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

$matchesFilter = static function (array $item) use ($type, $hostMode, $server): bool {
    if ($type === 'hosts') {
        if ($hostMode === 'hostp' && (string)($item['raw_bypassed'] ?? '') !== 'true') { return false; }
        if ($hostMode === 'hosta' && (string)($item['raw_authorized'] ?? '') !== 'true') { return false; }
        if ($server !== '' && (string)($item['server'] ?? '') !== $server) { return false; }
    } elseif ($type === 'ipbinding') {
        if ($server !== '' && (string)($item['server'] ?? '') !== $server) { return false; }
    }
    return true;
};

try {
    $cacheKey = $session . '|' . $type;
    $cached = !$fresh ? mikhmon_live_cache_read('live-hotspot', $cacheKey, 10) : null;
    if (is_array($cached) && isset($cached['rows']) && is_array($cached['rows'])) {
        echo json_encode(array('type' => 'meta', 'source' => 'cache', 'list_type' => $type)) . "\n"; flush();
        foreach ($cached['rows'] as $item) {
            if (!is_array($item) || !$matchesFilter($item)) { continue; }
            $count++;
            $out = $item;
            unset($out['raw_bypassed'], $out['raw_authorized']);
            echo json_encode(array('type' => 'row', 'data' => $out), JSON_UNESCAPED_SLASHES) . "\n";
        }
        echo json_encode(array('type' => 'done', 'rows' => $count, 'complete' => true, 'error' => '')) . "\n";
        flush();
        exit;
    }

    $allRows = array();
    $client = (new \Mikhmon\Router\RouterClientFactory(mikhmon_database()))->forSession($session);
    $client->connect();
    echo json_encode(array('type' => 'meta', 'source' => 'router', 'list_type' => $type)) . "\n"; flush();
    $result = $client->stream($command, array('.proplist' => $fields), function (array $row) use ($type, $matchesFilter, &$count, &$allRows) {
        if ($type === 'hosts') {
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
                'raw_bypassed' => (string)($row['bypassed'] ?? ''), 'raw_authorized' => (string)($row['authorized'] ?? ''),
            );
        } elseif ($type === 'cookies') {
            $data = array(
                'id' => (string)($row['.id'] ?? ''), 'user' => (string)($row['user'] ?? ''),
                'mac' => (string)($row['mac-address'] ?? ''), 'domain' => (string)($row['domain'] ?? ''),
                'expires' => formatDTM((string)($row['expires-in'] ?? '')),
            );
        } else {
            $data = array(
                'id' => (string)($row['.id'] ?? ''), 'mac' => (string)($row['mac-address'] ?? ''),
                'address' => (string)($row['address'] ?? ''), 'to_address' => (string)($row['to-address'] ?? ''),
                'server' => (string)($row['server'] ?? ''), 'comment' => (string)($row['comment'] ?? ''),
                'bypassed' => (string)($row['bypassed'] ?? ''), 'disabled' => (string)($row['disabled'] ?? ''),
            );
        }
        $allRows[] = $data;
        if (!$matchesFilter($data)) { return true; }
        $count++;
        $out = $data;
        unset($out['raw_bypassed'], $out['raw_authorized']);
        echo json_encode(array('type' => 'row', 'data' => $out), JSON_UNESCAPED_SLASHES) . "\n";
        if ($count % 100 === 0) { echo json_encode(array('type' => 'progress', 'rows' => $count)) . "\n"; flush(); }
        return true;
    }, 120.0);
    $client->close();
    if ($result->ok) {
        mikhmon_live_cache_write('live-hotspot', $cacheKey, array('rows' => $allRows, 'cached_at' => time()));
    }
    echo json_encode(array('type' => 'done', 'rows' => $count, 'complete' => $result->ok, 'error' => $result->error)) . "\n";
} catch (Throwable $e) {
    echo json_encode(array('type' => 'error', 'error' => $e->getMessage())) . "\n";
}
flush();
