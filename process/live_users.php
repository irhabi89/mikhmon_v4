<?php
/**
 * Read-only progressive user list (NDJSON). No database writes, no sync job.
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
    echo json_encode(array('type' => 'error', 'error' => 'unauthorized')) . "\n";
    flush();
    exit;
}
require_once(__DIR__ . '/../include/bootstrap.php');
require_once(__DIR__ . '/../lib/router.php');
require_once(__DIR__ . '/../lib/formatbytesbites.php');

$session = isset($_GET['session']) ? (string)$_GET['session'] : '';
if ($session === '') {
    echo json_encode(array('type' => 'error', 'error' => 'session required')) . "\n";
    flush();
    exit;
}
$prof = isset($_GET['profile']) ? (string)$_GET['profile'] : '';
$comm = isset($_GET['comment']) ? (string)$_GET['comment'] : '';
$exp = isset($_GET['exp']) ? (string)$_GET['exp'] : '';
$fields = '.id,server,name,password,profile,mac-address,uptime,bytes-in,bytes-out,comment,disabled,limit-uptime,limit-bytes-total';
$count = 0;
$profiles = array();

try {
    $client = (new \Mikhmon\Router\RouterClientFactory(mikhmon_database()))->forSession($session);
    $client->connect();
    echo json_encode(array('type' => 'meta', 'source' => 'router', 'session' => $session)) . "\n";
    flush();
    $result = $client->stream('/ip/hotspot/user/print', array('.proplist' => $fields), function (array $row) use ($prof, $comm, $exp, &$count, &$profiles) {
        $p = isset($row['profile']) ? (string)$row['profile'] : '';
        $c = isset($row['comment']) ? (string)$row['comment'] : '';
        $limit = isset($row['limit-uptime']) ? (string)$row['limit-uptime'] : '';
        if ($prof !== '' && $prof !== 'all' && $p !== $prof) { return true; }
        if ($comm !== '' && $c !== $comm) { return true; }
        if ($exp !== '' && $limit !== '1s') { return true; }
        $name = isset($row['name']) ? (string)$row['name'] : '';
        $pass = isset($row['password']) ? (string)$row['password'] : '';
        $bytesIn = isset($row['bytes-in']) ? (int)$row['bytes-in'] : 0;
        $bytesOut = isset($row['bytes-out']) ? (int)$row['bytes-out'] : 0;
        $dataLimit = isset($row['limit-bytes-total']) ? (int)$row['limit-bytes-total'] : 0;
        $profiles[$p] = isset($profiles[$p]) ? $profiles[$p] + 1 : 1;
        $count++;
        echo json_encode(array('type' => 'row', 'data' => array(
            'id' => isset($row['.id']) ? (string)$row['.id'] : '',
            'server' => isset($row['server']) ? (string)$row['server'] : '',
            'name' => $name,
            'profile' => $p,
            'mac' => isset($row['mac-address']) ? (string)$row['mac-address'] : '',
            'uptime' => function_exists('formatDTM') ? formatDTM(isset($row['uptime']) ? (string)$row['uptime'] : '') : (isset($row['uptime']) ? (string)$row['uptime'] : ''),
            'bytes_in' => function_exists('formatBytes') ? formatBytes($bytesIn, 2) : (string)$bytesIn,
            'bytes_out' => function_exists('formatBytes') ? formatBytes($bytesOut, 2) : (string)$bytesOut,
            'comment' => $c,
            'disabled' => isset($row['disabled']) ? (string)$row['disabled'] : '',
            'user_mode' => ($name !== '' && $name === $pass) ? 'vc' : 'up',
            'limit_uptime' => $limit === '1s' ? ' expired' : (' ' . $limit),
            'limit_data' => $dataLimit > 0 ? (' ' . formatBytes($dataLimit, 2)) : '',
        )), JSON_UNESCAPED_SLASHES) . "\n";
        if ($count % 100 === 0) { echo json_encode(array('type' => 'progress', 'rows' => $count)) . "\n"; flush(); }
        return true;
    }, 120.0);
    $client->close();
    echo json_encode(array('type' => 'profiles', 'data' => $profiles)) . "\n";
    echo json_encode(array('type' => 'done', 'rows' => $count, 'complete' => $result->ok, 'error' => $result->error)) . "\n";
} catch (Throwable $e) {
    echo json_encode(array('type' => 'error', 'error' => $e->getMessage())) . "\n";
}
flush();
