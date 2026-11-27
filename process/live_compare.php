<?php
/** Cached live-vs-database comparison produced by process/live_sales.php. */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!isset($_SESSION['mikhmon'])) { http_response_code(401); echo json_encode(array('ok'=>false,'error'=>'belum login')); exit; }
require_once(__DIR__ . '/../include/bootstrap.php');
mikhmon_require_schema();
$session = isset($_GET['session']) ? (string)$_GET['session'] : '';
if ($session === '' || preg_match('/^[A-Za-z0-9_.-]+$/', $session) !== 1) {
    http_response_code(400); echo json_encode(array('ok'=>false,'error'=>'session tidak valid')); exit;
}
$file = dirname(__DIR__) . '/data/cache/live-compare-' . hash('sha256', $session) . '.json';
if (!is_file($file)) { echo json_encode(array('ok'=>false,'error'=>'belum ada data live')); exit; }
$data = json_decode((string)@file_get_contents($file), true);
if (!is_array($data)) { echo json_encode(array('ok'=>false,'error'=>'cache tidak valid')); exit; }
$data['ok'] = true;
$data['cached'] = true;
echo json_encode($data, JSON_UNESCAPED_SLASHES);
