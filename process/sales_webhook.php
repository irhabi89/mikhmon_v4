<?php
/**
 * On-login sales webhook.
 *
 * RouterOS calls this endpoint after a successful voucher login. The body is
 * the raw sales record name. The session token is sent in X-Mikhmon-Token.
 * The endpoint is idempotent because sales_records has UNIQUE(session,name).
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

try {
    require_once(__DIR__ . '/../include/bootstrap.php');
    require_once(__DIR__ . '/../lib/router.php');
    mikhmon_require_schema();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'error' => $e->getMessage()));
    exit;
}

$session = isset($_GET['session']) ? (string)$_GET['session'] : '';
$token = isset($_SERVER['HTTP_X_MIKHMON_TOKEN']) ? (string)$_SERVER['HTTP_X_MIKHMON_TOKEN'] : '';
if ($session === '' || preg_match('/^[A-Za-z0-9_.-]+$/', $session) !== 1) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'error' => 'session tidak valid'));
    exit;
}

$row = mikhmon_session_get($session);
if (!is_array($row)) {
    http_response_code(404);
    echo json_encode(array('ok' => false, 'error' => 'session tidak ditemukan'));
    exit;
}
$expected = isset($row['webhook_token']) ? (string)$row['webhook_token'] : '';
if ($expected === '' || $token === '' || !hash_equals($expected, $token)) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'error' => 'token tidak valid'));
    exit;
}

$record = file_get_contents('php://input', false, null, 0, 16384);
if (!is_string($record) || trim($record) === '') {
    $record = isset($_POST['record']) ? (string)$_POST['record'] : '';
}
$record = trim($record);
if ($record === '' || !\Mikhmon\Support\RecordParser::isSalesRecord($record, 'mikhmon')) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'error' => 'record tidak valid'));
    exit;
}
$parsed = \Mikhmon\Support\RecordParser::parse($record);
if ($parsed === null) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'error' => 'record tidak dapat diparse'));
    exit;
}

try {
    $db = mikhmon_database();
    $sales = new \Mikhmon\Storage\SalesRepository($db);
    $inserted = $sales->insertParsed($session, $record, $parsed, 'webhook', 'mikhmon');
    echo json_encode(array('ok' => true, 'inserted' => (bool)$inserted, 'session' => $session));
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'error' => $e->getMessage()));
}
