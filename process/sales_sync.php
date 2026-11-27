<?php
/*
 * JSON endpoint for the sales sync UI.
 *
 * Actions: status, start, pause, cancel, scan_month, import_chunk, verify,
 *          probe, count_only, delete, prune_db.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_SESSION['mikhmon'])) {
    http_response_code(401);
    echo json_encode(array('ok' => false, 'error' => 'belum login'));
    exit;
}

try {
    require_once __DIR__ . '/../include/bootstrap.php';
    mikhmon_require_schema();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'error' => $e->getMessage()));
    exit;
}

$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
$session = isset($_POST['session']) ? (string)$_POST['session'] : '';
$runId = isset($_POST['run_id']) ? (int)$_POST['run_id'] : 0;
$month = isset($_POST['month']) ? (string)$_POST['month'] : '';
$csrf = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';

if ($session === '' || preg_match('/^[A-Za-z0-9_.-]+$/', $session) !== 1) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'error' => 'session tidak valid'));
    exit;
}

if ($action !== 'status' && !\Mikhmon\Http\Csrf::check($csrf)) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'error' => 'csrf token tidak valid'));
    exit;
}

// The long-running endpoints do not need the PHP session lock.
$csrfToken = \Mikhmon\Http\Csrf::token();
session_write_close();

set_time_limit(0);
@ini_set('memory_limit', '256M');

try {
    $service = new \Mikhmon\Sync\SalesSyncService(mikhmon_database());
    switch ($action) {
        case 'status':
            $result = $service->status($session);
            $result['csrf'] = $csrfToken;
            echo json_encode($result, JSON_UNESCAPED_SLASHES);
            break;
        case 'start':
            $deleteAfterImport = isset($_POST['delete_after_import']) && (string)$_POST['delete_after_import'] === '1';
            echo json_encode($service->start($session, array(
                'month' => $month,
                'delete_after_import' => $deleteAfterImport,
            )), JSON_UNESCAPED_SLASHES);
            break;
        case 'pause':
        case 'cancel':
            echo json_encode($service->cancel($session, $runId), JSON_UNESCAPED_SLASHES);
            break;
        case 'scan_month':
            if ($runId <= 0 || $month === '') {
                throw new RuntimeException('run_id dan month wajib');
            }
            echo json_encode($service->scanMonth($session, $runId, $month), JSON_UNESCAPED_SLASHES);
            break;
        case 'import_chunk':
            if ($runId <= 0) {
                throw new RuntimeException('run_id wajib');
            }
            echo json_encode($service->importChunk($session, $runId), JSON_UNESCAPED_SLASHES);
            break;
        case 'verify':
            if ($runId <= 0) {
                throw new RuntimeException('run_id wajib');
            }
            echo json_encode($service->verify($session, $runId), JSON_UNESCAPED_SLASHES);
            break;
        case 'probe':
            echo json_encode($service->probe($session), JSON_UNESCAPED_SLASHES);
            break;
        case 'count_only':
            echo json_encode($service->countOnly($session), JSON_UNESCAPED_SLASHES);
            break;
        case 'delete':
            $deleteDb = isset($_POST['delete_db']) && $_POST['delete_db'] === '1';
            $deleteRouter = isset($_POST['delete_router']) && $_POST['delete_router'] === '1';
            if (!$deleteDb && !$deleteRouter) {
                throw new RuntimeException('pilih database dan/atau MikroTik');
            }
            $filter = array(
                'date_from' => isset($_POST['date_key']) ? (string)$_POST['date_key'] : null,
                'date_to' => isset($_POST['date_key']) ? (string)$_POST['date_key'] : null,
                'month_key' => isset($_POST['month_key']) ? (string)$_POST['month_key'] : null,
                'q' => isset($_POST['q']) ? (string)$_POST['q'] : null,
            );
            $deleted = $service->deleteData($session, $filter, $deleteDb, $deleteRouter);
            $status = $service->status($session);
            $status['deleted'] = $deleted;
            echo json_encode($status, JSON_UNESCAPED_SLASHES);
            break;
        case 'prune_db':
            $sales = new \Mikhmon\Storage\SalesRepository(mikhmon_database());
            $count = $sales->pruneNotInSnapshot($session);
            $status = $service->status($session);
            $status['pruned'] = $count;
            echo json_encode($status, JSON_UNESCAPED_SLASHES);
            break;
        default:
            http_response_code(400);
            echo json_encode(array('ok' => false, 'error' => 'aksi tidak dikenal'));
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'error' => $e->getMessage()));
}
