<?php
/**
 * JSON endpoint for 10-second DB-first sales report auto-refresh.
 * Returns paginated rows, KPI stats, and current sync summary for the active filter.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_SESSION['mikhmon'])) {
    http_response_code(401);
    echo json_encode(array('ok' => false, 'error' => 'belum login'));
    exit;
}

$session = isset($_GET['session']) ? (string)$_GET['session'] : '';
if ($session === '' || preg_match('/^[A-Za-z0-9_.-]+$/', $session) !== 1) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'error' => 'session tidak valid'));
    exit;
}

try {
    require_once __DIR__ . '/../include/bootstrap.php';
    require_once __DIR__ . '/../include/ui.php';
    require_once __DIR__ . '/../include/report_fallback.php';
    mikhmon_require_schema();

    $db = mikhmon_database();
    $sessionRow = $db->fetchOne('SELECT name, currency FROM mikhmon_sessions WHERE name = ?', array($session));
    if (!is_array($sessionRow)) {
        http_response_code(404);
        echo json_encode(array('ok' => false, 'error' => 'session tidak ditemukan'));
        exit;
    }

    $currency = isset($sessionRow['currency']) && $sessionRow['currency'] !== '' ? (string)$sessionRow['currency'] : 'Rp';
    $decimals = in_array(strtoupper($currency), array('RP', 'IDR', 'RP.'), true) ? 0 : 2;

    $sales = new \Mikhmon\Storage\SalesRepository($db);
    $service = new \Mikhmon\Sync\SalesSyncService($db);

    $filterMeta = \Mikhmon\Support\SalesFilter::fromQuery($_GET);
    $filter = $filterMeta['filter'];
    $page = \Mikhmon\Support\Pagination::page(isset($_GET['page']) ? $_GET['page'] : 1);
    $perPage = \Mikhmon\Support\Pagination::perPage(isset($_GET['per_page']) ? $_GET['per_page'] : 50);

    $data = $sales->paginate($session, $filter, $page, $perPage, 'date', 'desc');
    $stats = $sales->stats($session, $filterMeta['server'] !== '' ? $filterMeta['server'] : null);
    $status = $service->summary($session);

    $baseQuery = \Mikhmon\Support\SalesFilter::query($filterMeta, $session);
    $baseUrl = './?' . http_build_query($baseQuery);

    $rows = array();
    foreach ($data['rows'] as $row) {
        $rows[] = array(
            'date_key' => (string)($row['date_key'] ?? ''),
            'time_key' => (string)($row['time_key'] ?? ''),
            'server_name' => (string)($row['server_name'] ?? ''),
            'username' => (string)($row['username'] ?? ''),
            'profile' => (string)($row['profile'] ?? ''),
            'sale_comment' => (string)($row['sale_comment'] ?? ''),
            'price' => (float)($row['price'] ?? 0),
        );
    }

    session_write_close();

    echo json_encode(array(
        'ok' => true,
        'session' => $session,
        'currency' => $currency,
        'decimals' => $decimals,
        'period_label' => \Mikhmon\Support\SalesFilter::label($filterMeta),
        'total' => (int)$data['total'],
        'sum' => (float)$data['sum'],
        'page' => (int)$data['page'],
        'pages' => (int)$data['pages'],
        'perPage' => (int)$data['perPage'],
        'rows' => $rows,
        'pager_html' => mikhmon_pager((int)$data['total'], (int)$data['page'], (int)$data['perPage'], $baseUrl),
        'stats' => $stats,
        'status' => $status,
        'source_badge_html' => mikhmon_source_badge($status),
        'checked_at' => date('Y-m-d H:i:s'),
    ), JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(array(
        'ok' => false,
        'db_error' => true,
        'error' => $e->getMessage(),
    ), JSON_UNESCAPED_SLASHES);
}
