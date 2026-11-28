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
require_once(__DIR__ . '/../include/report_fallback.php');
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
$fresh = isset($_GET['fresh']) && (string)$_GET['fresh'] === '1';
$fields = '.id,server,name,password,profile,mac-address,uptime,bytes-in,bytes-out,comment,disabled,limit-uptime,limit-bytes-total';
$count = 0;
$profiles = array();

$matchesFilter = static function (array $item) use ($prof, $comm, $exp): bool {
    $p = (string)($item['profile'] ?? '');
    $c = (string)($item['comment'] ?? '');
    $rawLimit = (string)($item['raw_limit_uptime'] ?? '');
    if ($prof !== '' && $prof !== 'all' && $p !== $prof) { return false; }
    if ($comm !== '' && $c !== $comm) { return false; }
    if ($exp !== '' && $rawLimit !== '1s') { return false; }
    return true;
};

try {
    $cached = !$fresh ? mikhmon_live_cache_read('live-users', $session, 15) : null;
    if (is_array($cached) && isset($cached['rows']) && is_array($cached['rows'])) {
        echo json_encode(array('type' => 'meta', 'source' => 'cache', 'session' => $session)) . "\n";
        flush();
        foreach ($cached['rows'] as $item) {
            if (!is_array($item) || !$matchesFilter($item)) { continue; }
            $p = (string)($item['profile'] ?? '');
            $profiles[$p] = isset($profiles[$p]) ? $profiles[$p] + 1 : 1;
            $count++;
            $out = $item;
            unset($out['raw_limit_uptime']);
            echo json_encode(array('type' => 'row', 'data' => $out), JSON_UNESCAPED_SLASHES) . "\n";
        }
        echo json_encode(array('type' => 'profiles', 'data' => isset($cached['profiles']) && is_array($cached['profiles']) ? $cached['profiles'] : $profiles)) . "\n";
        echo json_encode(array('type' => 'done', 'rows' => $count, 'complete' => true, 'error' => '')) . "\n";
        flush();
        exit;
    }

    $allRows = array();
    $allProfiles = array();
    $client = (new \Mikhmon\Router\RouterClientFactory(mikhmon_database()))->forSession($session);
    $client->connect();
    echo json_encode(array('type' => 'meta', 'source' => 'router', 'session' => $session)) . "\n";
    flush();
    $result = $client->stream('/ip/hotspot/user/print', array('.proplist' => $fields), function (array $row) use ($matchesFilter, &$count, &$profiles, &$allRows, &$allProfiles) {
        $p = isset($row['profile']) ? (string)$row['profile'] : '';
        $c = isset($row['comment']) ? (string)$row['comment'] : '';
        $limit = isset($row['limit-uptime']) ? (string)$row['limit-uptime'] : '';
        $name = isset($row['name']) ? (string)$row['name'] : '';
        $pass = isset($row['password']) ? (string)$row['password'] : '';
        $bytesIn = isset($row['bytes-in']) ? (int)$row['bytes-in'] : 0;
        $bytesOut = isset($row['bytes-out']) ? (int)$row['bytes-out'] : 0;
        $dataLimit = isset($row['limit-bytes-total']) ? (int)$row['limit-bytes-total'] : 0;
        $allProfiles[$p] = isset($allProfiles[$p]) ? $allProfiles[$p] + 1 : 1;
        $item = array(
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
            'raw_limit_uptime' => $limit,
        );
        $allRows[] = $item;
        if (!$matchesFilter($item)) { return true; }
        $profiles[$p] = isset($profiles[$p]) ? $profiles[$p] + 1 : 1;
        $count++;
        $out = $item;
        unset($out['raw_limit_uptime']);
        echo json_encode(array('type' => 'row', 'data' => $out), JSON_UNESCAPED_SLASHES) . "\n";
        if ($count % 100 === 0) { echo json_encode(array('type' => 'progress', 'rows' => $count)) . "\n"; flush(); }
        return true;
    }, 120.0);
    $client->close();
    if ($result->ok) {
        mikhmon_live_cache_write('live-users', $session, array('rows' => $allRows, 'profiles' => $allProfiles, 'cached_at' => time()));
    }
    echo json_encode(array('type' => 'profiles', 'data' => !empty($allProfiles) ? $allProfiles : $profiles)) . "\n";
    echo json_encode(array('type' => 'done', 'rows' => $count, 'complete' => $result->ok, 'error' => $result->error)) . "\n";
} catch (Throwable $e) {
    echo json_encode(array('type' => 'error', 'error' => $e->getMessage())) . "\n";
}
flush();
