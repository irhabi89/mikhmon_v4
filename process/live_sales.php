<?php
/**
 * Read-only live sales stream used when sales_records is empty.
 * It never writes to SQLite and never starts a sync/background job.
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
mikhmon_require_schema();

$session = isset($_GET['session']) ? (string)$_GET['session'] : '';
if ($session === '') {
    echo json_encode(array('type' => 'error', 'error' => 'session required')) . "\n";
    flush();
    exit;
}
$summaryMode = isset($_GET['summary']) && (string)$_GET['summary'] === '1';
$streamSummary = $summaryMode && isset($_GET['stream']) && (string)$_GET['stream'] === '1';
$compareMode = isset($_GET['compare']) && (string)$_GET['compare'] === '1';
$meta = \Mikhmon\Support\SalesFilter::fromQuery($_GET);
$filter = $meta['filter'];

function mm_live_match(array $parsed, array $filter): bool
{
    $date = (string)($parsed['date_key'] ?? '');
    if (!empty($filter['date_from']) && $date < $filter['date_from']) { return false; }
    if (!empty($filter['date_to']) && $date > $filter['date_to']) { return false; }
    if (!empty($filter['month_key']) && (string)($parsed['month_key'] ?? '') !== $filter['month_key']) { return false; }
    if (!empty($filter['month_name']) && substr($date, 5, 2) !== $filter['month_name']) { return false; }
    if (!empty($filter['year']) && substr($date, 0, 4) !== $filter['year']) { return false; }
    if (!empty($filter['server'])) {
        $server = (string)($parsed['server_name'] ?? '');
        if ($filter['server'] === '__none__') {
            if ($server !== '') { return false; }
        } elseif ($server !== $filter['server']) { return false; }
    }
    if (!empty($filter['q'])) {
        $q = strtolower((string)$filter['q']);
        $hay = strtolower((string)($parsed['username'] ?? '') . ' ' . (string)($parsed['sale_comment'] ?? '') . ' ' . (string)($parsed['profile'] ?? ''));
        if (strpos($hay, $q) === false) { return false; }
    }
    return true;
}

$today = date('Y-m-d');
$month = date('Y-m');
$summary = array('today_count' => 0, 'today_sum' => 0.0, 'month_count' => 0, 'month_sum' => 0.0, 'total_count' => 0, 'total_sum' => 0.0);
$count = 0;
$started = microtime(true);
$compareTotal = 0;
$compareMatched = 0;
$compareSeen = array();
$dbTotal = 0;
$dbCheck = null;
if ($compareMode) {
    $db = mikhmon_database();
    $dbTotal = (int)$db->value('SELECT COUNT(*) FROM sales_records WHERE session = ?', array($session));
    $dbCheck = $db->pdo()->prepare('SELECT 1 FROM sales_records WHERE session = ? AND name = ? LIMIT 1');
}

try {
    $client = (new \Mikhmon\Router\RouterClientFactory(mikhmon_database()))->forSession($session);
    $client->connect();
    if (!$summaryMode) {
        echo json_encode(array('type' => 'meta', 'source' => 'router', 'session' => $session)) . "\n";
        flush();
    }
    $result = $client->stream('/system/script/print', array('.proplist' => '.id,name,comment'), function (array $row) use ($filter, $summaryMode, $compareMode, &$count, $today, $month, &$summary, &$compareTotal, &$compareMatched, &$compareSeen, $dbCheck, $session) {
        $name = isset($row['name']) ? (string)$row['name'] : '';
        $comment = isset($row['comment']) ? (string)$row['comment'] : '';
        if ($name === '' || !\Mikhmon\Support\RecordParser::isSalesRecord($name, $comment)) { return true; }
        if ($compareMode && !isset($compareSeen[$name])) {
            $compareSeen[$name] = true;
            $compareTotal++;
            if ($dbCheck !== null) {
                $dbCheck->execute(array($session, $name));
                if ($dbCheck->fetchColumn() !== false) {
                    $compareMatched++;
                }
            }
        }
        $parsed = \Mikhmon\Support\RecordParser::parse($name);
        if ($parsed === null || !mm_live_match($parsed, $filter)) { return true; }
        $count++;
        $price = (float)($parsed['price'] ?? 0);
        $summary['total_count']++;
        $summary['total_sum'] += $price;
        if (($parsed['date_key'] ?? '') === $today) { $summary['today_count']++; $summary['today_sum'] += $price; }
        if (($parsed['month_key'] ?? '') === $month) { $summary['month_count']++; $summary['month_sum'] += $price; }
        if ($summaryMode) {
            if ($streamSummary && $count % 100 === 0) {
                echo json_encode(array('type' => 'progress', 'rows' => $count, 'today_count' => $summary['today_count'], 'month_count' => $summary['month_count'])) . "\n";
                flush();
            }
            return true;
        }
        echo json_encode(array('type' => 'row', 'data' => array(
            'date_key' => (string)($parsed['date_key'] ?? ''),
            'time_key' => (string)($parsed['time_key'] ?? ''),
            'server_name' => (string)($parsed['server_name'] ?? ''),
            'username' => (string)($parsed['username'] ?? ''),
            'profile' => (string)($parsed['profile'] ?? ''),
            'sale_comment' => (string)($parsed['sale_comment'] ?? ''),
            'price' => $price,
            'name' => $name,
        )), JSON_UNESCAPED_SLASHES) . "\n";
        if ($count % 100 === 0) { echo json_encode(array('type' => 'progress', 'rows' => $count, 'seconds' => round(microtime(true) - $started, 1))) . "\n"; flush(); }
        return true;
    }, 120.0);
    $client->close();
    if ($summaryMode) {
        if ($streamSummary) {
            echo json_encode(array('type' => 'summary', 'ok' => true, 'source' => 'router', 'count' => $count, 'summary' => $summary)) . "\n";
            echo json_encode(array('type' => 'done', 'rows' => $count, 'complete' => $result->ok, 'error' => $result->error)) . "\n";
        } else {
            echo json_encode(array('ok' => true, 'source' => 'router', 'count' => $count, 'summary' => $summary)) . "\n";
        }
    } else {
        if ($compareMode) {
            $compareMissing = max(0, $compareTotal - $compareMatched);
            $compare = array(
                'type' => 'compare',
                'source' => 'live',
                'router_total' => $compareTotal,
                'db_total' => $dbTotal,
                'matched' => $compareMatched,
                'missing' => $compareMissing,
                'archive_extra' => max(0, $dbTotal - $compareMatched),
                'synced_percent' => $compareTotal > 0 ? round($compareMatched / $compareTotal * 100, 1) : null,
                'unsynced_percent' => $compareTotal > 0 ? round($compareMissing / $compareTotal * 100, 1) : null,
                'verified' => false,
                'complete' => (bool)$result->ok,
                'checked_at' => date('Y-m-d H:i:s'),
            );
            echo json_encode($compare, JSON_UNESCAPED_SLASHES) . "\n";
            if ($result->ok) {
                $cacheDir = dirname(__DIR__) . '/data/cache';
                if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0775, true); }
                @file_put_contents($cacheDir . '/live-compare-' . hash('sha256', $session) . '.json', json_encode($compare, JSON_UNESCAPED_SLASHES));
            }
        }
        echo json_encode(array('type' => 'done', 'rows' => $count, 'complete' => $result->ok, 'error' => $result->error)) . "\n";
    }
} catch (Throwable $e) {
    if ($summaryMode) {
        echo json_encode(array('ok' => false, 'error' => $e->getMessage())) . "\n";
    } else {
        echo json_encode(array('type' => 'error', 'error' => $e->getMessage())) . "\n";
    }
}
flush();
