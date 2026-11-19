<?php
/*
 * Mikhmon v3: AJAX endpoint for the report page Import modal.
 * Actions: status, prepare, chunk, delete.
 */
session_start();
error_reporting(0);
header('Content-Type: application/json');
if (!isset($_SESSION['mikhmon'])) {
    echo json_encode(array('ok' => false, 'error' => 'belum login'));
    exit;
}
require_once __DIR__ . '/../lib/sales_import.php';

$session = isset($_POST['session']) ? (string)$_POST['session'] : '';
if ($session === '') {
    echo json_encode(array('ok' => false, 'error' => 'session required'));
    exit;
}
set_time_limit(0);
@ini_set('memory_limit', '512M');

$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
switch ($action) {
    case 'status':
        echo json_encode(mikhmon_import_status($session));
        break;
    case 'prepare':
        echo json_encode(mikhmon_import_prepare($session));
        break;
    case 'chunk':
        $batch = isset($_POST['batch']) ? (int)$_POST['batch'] : 500;
        echo json_encode(mikhmon_import_chunk($session, $batch));
        break;
    case 'delete':
        $batch = isset($_POST['batch']) ? (int)$_POST['batch'] : 300;
        echo json_encode(mikhmon_import_delete($session, $batch));
        break;
    default:
        echo json_encode(array('ok' => false, 'error' => 'aksi tidak dikenal'));
}
