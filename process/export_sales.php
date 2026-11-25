<?php
/* Stream the filtered selling report as CSV. */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['mikhmon'])) {
    http_response_code(401);
    exit('belum login');
}

try {
    require_once __DIR__ . '/../include/bootstrap.php';
    mikhmon_require_schema();
} catch (Throwable $e) {
    http_response_code(500);
    exit($e->getMessage());
}

$token = isset($_GET['token']) ? (string)$_GET['token'] : '';
if (!\Mikhmon\Http\Csrf::check($token)) {
    http_response_code(403);
    exit('token tidak valid');
}

$session = isset($_GET['session']) ? (string)$_GET['session'] : '';
if ($session === '' || preg_match('/^[A-Za-z0-9_.-]+$/', $session) !== 1) {
    http_response_code(400);
    exit('session tidak valid');
}

$dateKey = null;
if (!empty($_GET['date_key']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['date_key'])) {
    $dateKey = (string)$_GET['date_key'];
} elseif (!empty($_GET['idhr'])) {
    $dateKey = \Mikhmon\Support\RecordParser::dateKeyFromLegacy((string)$_GET['idhr']);
}
$monthKey = null;
if (!empty($_GET['month_key']) && preg_match('/^\d{4}-\d{2}$/', (string)$_GET['month_key'])) {
    $monthKey = (string)$_GET['month_key'];
} elseif (!empty($_GET['idbl'])) {
    $monthKey = \Mikhmon\Support\RecordParser::monthKeyFromBucket((string)$_GET['idbl']);
}
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

session_write_close();
set_time_limit(0);
@ini_set('memory_limit', '256M');

$db = mikhmon_database();
$sales = new \Mikhmon\Storage\SalesRepository($db);
$filter = array(
    'date_from' => $dateKey,
    'date_to' => $dateKey,
    'month_key' => $dateKey !== null ? null : $monthKey,
    'q' => $q,
);
$st = $sales->exportStatement($session, $filter);

$filename = 'selling-' . $session . '-' . ($dateKey !== null ? $dateKey : ($monthKey !== null ? $monthKey : 'all')) . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fputcsv($out, array('date', 'time', 'username', 'profile', 'comment', 'address', 'mac', 'validity', 'price', 'name'), ',', '"', '');
$max = 1000000;
$n = 0;
while (($row = $st->fetch(PDO::FETCH_ASSOC)) !== false) {
    fputcsv($out, array(
        $row['date_key'],
        $row['time_key'],
        $row['username'],
        $row['profile'],
        $row['sale_comment'],
        $row['address'],
        $row['mac'],
        $row['validity'],
        $row['price'],
        $row['name'],
    ), ',', '"', '');
    $n++;
    if ($n >= $max) {
        break;
    }
}
fclose($out);
