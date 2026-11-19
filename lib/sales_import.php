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

/* ---- Chunked, pausable import for the report page (queue persisted in SQLite) ---- */

function mikhmon_import_queue_count($session, $done = null)
{
    $db = mikhmon_db();
    if ($db === false) {
        return 0;
    }
    $sql = 'SELECT COUNT(*) FROM sales_import_queue WHERE session = ?';
    $vals = array($session);
    if ($done !== null) {
        $sql .= ' AND done = ?';
        $vals[] = (int)$done;
    }
    try {
        $st = $db->prepare($sql);
        $st->execute($vals);
        return (int)$st->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function mikhmon_import_router_total($session)
{
    $row = mikhmon_session_get((string)$session);
    if (!is_array($row)) {
        return -1;
    }
    $API = new RouterosAPI();
    $API->debug = false;
    if (!$API->connect($row['ip_host'], $row['user_host'], decrypt($row['pass_host']))) {
        return -1;
    }
    $c = $API->comm("/system/script/print", array("?comment" => "mikhmon", "count-only" => ""));
    $API->disconnect();
    if (is_array($c)) {
        $c = reset($c);
    }
    return is_numeric($c) ? (int)$c : -1;
}

function mikhmon_import_status($session)
{
    $router_total = mikhmon_import_router_total($session);
    $db_count = mikhmon_sales_count($session);
    $percent = ($router_total > 0) ? min(100.0, round($db_count / $router_total * 100, 1)) : 0.0;
    return array(
        'ok' => true,
        'router_total' => $router_total,
        'db_count' => $db_count,
        'percent' => $percent,
        'queue_total' => mikhmon_import_queue_count($session),
        'queue_done' => mikhmon_import_queue_count($session, 1),
    );
}

function mikhmon_import_prepare($session)
{
    $row = mikhmon_session_get((string)$session);
    if (!is_array($row)) {
        return array('ok' => false, 'error' => 'router tidak ada di database');
    }
    $API = new RouterosAPI();
    $API->debug = false;
    if (!$API->connect($row['ip_host'], $row['user_host'], decrypt($row['pass_host']))) {
        return array('ok' => false, 'error' => 'gagal terhubung ke Mikrotik');
    }
    $names = $API->comm("/system/script/print", array("?comment" => "mikhmon", ".proplist" => "name"));
    $API->disconnect();
    if (!is_array($names)) {
        return array('ok' => false, 'error' => 'gagal membaca daftar script');
    }
    $db = mikhmon_db();
    if ($db === false) {
        return array('ok' => false, 'error' => 'database tidak tersedia');
    }
    $prepared = 0;
    try {
        $db->beginTransaction();
        $db->prepare('DELETE FROM sales_import_queue WHERE session = ?')->execute(array($session));
        $st = $db->prepare('INSERT OR IGNORE INTO sales_import_queue (session, name, done) VALUES (?, ?, 0)');
        foreach ($names as $n) {
            if (!isset($n['name'])) {
                continue;
            }
            $st->execute(array($session, (string)$n['name']));
            $prepared++;
        }
        // Records already stored in the DB don't need to be processed again.
        $db->prepare('UPDATE sales_import_queue SET done = 1 WHERE session = ? AND name IN (SELECT name FROM sales_records WHERE session = ?)')
            ->execute(array($session, $session));
        $db->commit();
    } catch (PDOException $e) {
        try { $db->rollBack(); } catch (PDOException $e2) {}
        return array('ok' => false, 'error' => 'gagal menyiapkan antrean');
    }
    $st = mikhmon_import_status($session);
    $st['prepared'] = $prepared;
    return $st;
}

function mikhmon_import_chunk($session, $batch = 500)
{
    $db = mikhmon_db();
    if ($db === false) {
        return array('ok' => false, 'error' => 'database tidak tersedia');
    }
    $batch = max(1, (int)$batch);
    $processed = 0;
    try {
        $q = $db->prepare('SELECT name FROM sales_import_queue WHERE session = ? AND done = 0 LIMIT ' . $batch);
        $q->execute(array($session));
        $pending = $q->fetchAll(PDO::FETCH_ASSOC);
        if (empty($pending)) {
            $st = mikhmon_import_status($session);
            $st['processed'] = 0;
            return $st;
        }
        $db->beginTransaction();
        $upd = $db->prepare('UPDATE sales_import_queue SET done = 1 WHERE session = ? AND name = ?');
        foreach ($pending as $p) {
            $name = (string)$p['name'];
            $f = mikhmon_script_parse($name);
            mikhmon_sales_insert(array(
                'session' => (string)$session,
                'ros_script_id' => '',
                'name' => $name,
                'source' => $f[0],
                'owner' => mikhmon_script_month($name),
                'comment' => 'mikhmon',
                'c_date' => $f[0],
                'c_time' => $f[1],
                'c_username' => $f[2],
                'c_price' => $f[3],
                'c_address' => $f[4],
                'c_mac' => $f[5],
                'c_validity' => $f[6],
                'c_profile' => $f[7],
                'c_comment' => $f[8],
            ));
            $upd->execute(array($session, $name));
            $processed++;
        }
        $db->commit();
    } catch (PDOException $e) {
        try { $db->rollBack(); } catch (PDOException $e2) {}
        return array('ok' => false, 'error' => 'gagal import');
    }
    $st = mikhmon_import_status($session);
    $st['processed'] = $processed;
    return $st;
}

// Delete router sales scripts for a period (month bucket / source date) or all.
function mikhmon_import_delete_period($session, $source = null, $owner = null)
{
    $row = mikhmon_session_get((string)$session);
    if (!is_array($row)) {
        return array('ok' => false, 'error' => 'router tidak ada di database');
    }
    $API = new RouterosAPI();
    $API->debug = false;
    if (!$API->connect($row['ip_host'], $row['user_host'], decrypt($row['pass_host']))) {
        return array('ok' => false, 'error' => 'gagal terhubung ke Mikrotik');
    }
    $deleted = 0;
    if ($source === null && $owner === null) {
        $ids = $API->comm("/system/script/print", array("?comment" => "mikhmon", ".proplist" => ".id"));
        foreach ((array)$ids as $idr) {
            if (!isset($idr['.id'])) {
                continue;
            }
            $r = $API->comm("/system/script/remove", array('.id' => $idr['.id']));
            if (!is_array($r) || !isset($r['!trap'])) {
                $deleted++;
            }
        }
    } else {
        $names = $API->comm("/system/script/print", array("?comment" => "mikhmon", ".proplist" => "name"));
        foreach ((array)$names as $n) {
            if (!isset($n['name'])) {
                continue;
            }
            $name = (string)$n['name'];
            $match = ($owner !== null && mikhmon_script_month($name) === (string)$owner)
                || ($source !== null && mikhmon_script_parse($name)[0] === (string)$source);
            if (!$match) {
                continue;
            }
            $ids = $API->comm("/system/script/print", array("?comment" => "mikhmon", "?name" => $name, ".proplist" => ".id"));
            foreach ((array)$ids as $idr) {
                if (!isset($idr['.id'])) {
                    continue;
                }
                $r = $API->comm("/system/script/remove", array('.id' => $idr['.id']));
                if (!is_array($r) || !isset($r['!trap'])) {
                    $deleted++;
                }
            }
        }
    }
    $API->disconnect();
    return array('ok' => true, 'deleted' => $deleted);
}

function mikhmon_import_delete($session, $batch = 300)
{
    $row = mikhmon_session_get((string)$session);
    if (!is_array($row)) {
        return array('ok' => false, 'error' => 'router tidak ada di database');
    }
    $API = new RouterosAPI();
    $API->debug = false;
    if (!$API->connect($row['ip_host'], $row['user_host'], decrypt($row['pass_host']))) {
        return array('ok' => false, 'error' => 'gagal terhubung ke Mikrotik');
    }
    $ids = $API->comm("/system/script/print", array("?comment" => "mikhmon", ".proplist" => ".id"));
    $deleted = 0;
    $remaining = 0;
    foreach ((array)$ids as $idr) {
        if (!isset($idr['.id'])) {
            continue;
        }
        if ($deleted >= $batch) {
            $remaining++;
            continue;
        }
        $r = $API->comm("/system/script/remove", array('.id' => $idr['.id']));
        if (!is_array($r) || !isset($r['!trap'])) {
            $deleted++;
        } else {
            $remaining++;
        }
    }
    $API->disconnect();
    return array('ok' => true, 'deleted' => $deleted, 'remaining' => $remaining);
}
