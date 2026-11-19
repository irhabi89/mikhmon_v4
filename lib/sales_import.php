<?php
/*
 * Mikhmon v3: sales import (router -> SQLite), shared by the CLI migration
 * (migrations/import_sales.php) and the report page "Import" panel.
 *
 * Sales are stored on the Mikrotik as /system/script entries with
 * comment=mikhmon; the record name encodes the sale ("-|-" separated):
 *   date-|-time-|-user-|-price-|-address-|-mac-|-validity-|-profile-|-comment
 * (see include/mikhmon_records.php). RouterOS 7.24+ uses owner=*sys; the
 * stored owner is computed from the record date instead.
 *
 * $opts: dry_run (bool), delete (bool), month (MMMYYYY|null)
 * Returns: array(ok, total, imported, deleted, error)
 */
require_once __DIR__ . '/mikhmon_db.php';
require_once __DIR__ . '/sessions_store.php';
require_once __DIR__ . '/routeros_api.class.php';
require_once dirname(__DIR__) . '/include/mikhmon_records.php';

function mikhmon_import_sales_session($session, $opts = array())
{
    $opts = array_merge(array('dry_run' => false, 'delete' => false, 'month' => null), (array)$opts);
    $result = array('ok' => false, 'total' => 0, 'imported' => 0, 'deleted' => 0, 'error' => '');

    $row = mikhmon_session_get((string)$session);
    if (!is_array($row)) {
        $result['error'] = 'router tidak ada di database (import config dulu)';
        return $result;
    }
    $API = new RouterosAPI();
    $API->debug = false;
    if (!$API->connect($row['ip_host'], $row['user_host'], decrypt($row['pass_host']))) {
        $result['error'] = 'gagal terhubung ke Mikrotik';
        return $result;
    }
    if (!empty($opts['delete'])) {
        // Deletion needs the router .id, so fetch full rows (heavy on big routers).
        $scripts = $API->comm("/system/script/print", array("?comment" => "mikhmon"));
    } else {
        // No deletion: only record names are needed (app-safe memory footprint).
        $scripts = $API->comm("/system/script/print", array("?comment" => "mikhmon", ".proplist" => "name"));
    }
    $result['total'] = mikhmon_count_rows($scripts);
    $importedIds = array();

    // Batch inserts in one transaction: per-row autocommit is very slow on
    // routers with tens of thousands of records.
    $db = mikhmon_db();
    $in_tx = false;
    if ($db !== false) {
        try {
            $in_tx = $db->beginTransaction();
        } catch (PDOException $e) {
            $in_tx = false;
        }
    }
    foreach ($scripts as $s) {
        if (!isset($s['name'])) {
            continue;
        }
        $name = (string)$s['name'];
        if ($opts['month'] !== null && mikhmon_script_month($name) !== $opts['month']) {
            continue;
        }
        $fields = mikhmon_script_parse($name);
        $record = array(
            'session' => (string)$session,
            'ros_script_id' => isset($s['.id']) ? (string)$s['.id'] : '',
            'name' => $name,
            'source' => $fields[0],
            'owner' => mikhmon_script_month($name),
            'comment' => isset($s['comment']) ? (string)$s['comment'] : 'mikhmon',
            'c_date' => $fields[0],
            'c_time' => $fields[1],
            'c_username' => $fields[2],
            'c_price' => $fields[3],
            'c_address' => $fields[4],
            'c_mac' => $fields[5],
            'c_validity' => $fields[6],
            'c_profile' => $fields[7],
            'c_comment' => $fields[8],
        );
        if (!empty($opts['dry_run'])) {
            continue;
        }
        $id = mikhmon_sales_insert($record);
        if ($id > 0) {
            $result['imported']++;
            if (!empty($record['ros_script_id'])) {
                $importedIds[] = $record['ros_script_id'];
            }
        }
    }
    if ($in_tx) {
        try {
            $db->commit();
        } catch (PDOException $e) {
        }
    }

    if (!empty($opts['delete']) && !empty($importedIds)) {
        foreach ($importedIds as $rid) {
            $r = $API->comm("/system/script/remove", array('.id' => $rid));
            if (!is_array($r) || !isset($r['!trap'])) {
                $result['deleted']++;
            }
        }
    }
    $API->disconnect();
    $result['ok'] = true;
    return $result;
}
