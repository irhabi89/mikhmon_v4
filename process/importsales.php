<?php
/*
 * Backwards-compatible shim for the old import modal.
 * New UI uses process/sales_sync.php with run ids and CSRF tokens.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(0);
header('Content-Type: application/json');
if (!isset($_SESSION['mikhmon'])) {
    echo json_encode(array('ok' => false, 'error' => 'belum login'));
    exit;
}
try {
    require_once __DIR__ . '/../include/bootstrap.php';
    mikhmon_require_schema();
} catch (Throwable $e) {
    echo json_encode(array('ok' => false, 'error' => $e->getMessage()));
    exit;
}
session_write_close();
set_time_limit(0);

$session = isset($_POST['session']) ? (string)$_POST['session'] : '';
$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
$runId = isset($_POST['run_id']) ? (int)$_POST['run_id'] : 0;
$service = new \Mikhmon\Sync\SalesSyncService(mikhmon_database());
try {
    if ($action === 'status') {
        echo json_encode($service->status($session), JSON_UNESCAPED_SLASHES);
    } elseif ($action === 'prepare') {
        echo json_encode($service->start($session), JSON_UNESCAPED_SLASHES);
    } elseif ($action === 'chunk' && $runId > 0) {
        echo json_encode($service->importChunk($session, $runId), JSON_UNESCAPED_SLASHES);
    } else {
        echo json_encode(array('ok' => false, 'error' => 'gunakan process/sales_sync.php'));
    }
} catch (Throwable $e) {
    echo json_encode(array('ok' => false, 'error' => $e->getMessage()));
}
