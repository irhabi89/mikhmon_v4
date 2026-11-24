<?php
/*
 * Mikhmon v3 storage adapter over SQLite (Phase 1).
 *
 * Mirrors the old $data formats: session rows keep the same field names as
 * readcfg.php produces; sales records mirror the parsed columns of the
 * /system/script record names ("Nov/19/2026-|-...").
 *
 * PHP 7.4-safe. Functions degrade gracefully (null/[]/0/false) when the DB
 * is unavailable.
 */

function mikhmon_row_from_data($name, $dataRow)
{
    if (!is_array($dataRow)) {
        $dataRow = array();
    }
    $val = function ($idx, $delim) use ($dataRow) {
        $v = isset($dataRow[$idx]) ? $dataRow[$idx] : '';
        if (!is_string($v) || $delim === '') {
            return '';
        }
        $p = explode($delim, $v, 2);
        return isset($p[1]) ? $p[1] : '';
    };
    if ((string)$name === 'mikhmon') {
        // Mikhmon admin entry: readcfg.php parses user from [1] with '<|<'
        // and password from [2] with '>|>'.
        return array(
            'name' => 'mikhmon',
            'user_host' => $val(1, '<|<'),
            'pass_host' => $val(2, '>|>'),
        );
    }
    $areload = $val(7, '*');
    if ($areload === '' || !is_numeric($areload)) {
        $areload = '10';
    }
    return array(
        'name' => (string)$name,
        'ip_host' => $val(1, '!'),
        'user_host' => $val(2, '@|@'),
        'pass_host' => $val(3, '#|#'),
        'hotspot_name' => $val(4, '%'),
        'dns_name' => $val(5, '^'),
        'currency' => $val(6, '&'),
        'areload' => $areload,
        'iface' => $val(8, '('),
        'infolp' => $val(9, ')'),
        'idleto' => $val(10, '='),
        'live_report' => $val(11, '@!@'),
        'raw' => serialize($dataRow),
    );
}

function mikhmon_sessions_all()
{
    $db = mikhmon_db();
    if ($db === false) {
        return array();
    }
    try {
        $names = array();
        $st = $db->query('SELECT name FROM mikhmon_sessions ORDER BY name');
        foreach ($st as $r) {
            $names[] = $r['name'];
        }
        return $names;
    } catch (PDOException $e) {
        return array();
    }
}

function mikhmon_session_get($name)
{
    $db = mikhmon_db();
    if ($db === false) {
        return null;
    }
    try {
        $st = $db->prepare('SELECT * FROM mikhmon_sessions WHERE name = ?');
        $st->execute(array($name));
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r === false ? null : $r;
    } catch (PDOException $e) {
        return null;
    }
}

function mikhmon_session_upsert($fields)
{
    $db = mikhmon_db();
    if ($db === false) {
        return false;
    }
    $allowed = array('name', 'ip_host', 'user_host', 'pass_host', 'hotspot_name',
        'dns_name', 'currency', 'areload', 'iface', 'infolp', 'idleto',
        'live_report', 'raw');
    $fields = array_intersect_key($fields, array_flip($allowed));
    if (empty($fields['name'])) {
        return false;
    }
    try {
        $row = mikhmon_session_get($fields['name']);
        $full = array('name' => $fields['name']);
        foreach ($allowed as $k) {
            $full[$k] = isset($row[$k]) ? $row[$k] : '';
        }
        foreach ($fields as $k => $v) {
            $full[$k] = $v;
        }
        $cols = array();
        $vals = array();
        foreach ($allowed as $k) {
            $cols[] = '"' . $k . '"';
            $vals[] = $full[$k];
        }
        $db->prepare('INSERT OR REPLACE INTO mikhmon_sessions (' . implode(',', $cols)
            . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute($vals);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function mikhmon_session_delete($name)
{
    $db = mikhmon_db();
    if ($db === false) {
        return false;
    }
    try {
        return $db->prepare('DELETE FROM mikhmon_sessions WHERE name = ?')->execute(array($name));
    } catch (PDOException $e) {
        return false;
    }
}

function mikhmon_admin_get()
{
    $db = mikhmon_db();
    if ($db === false) {
        return array();
    }
    try {
        $st = $db->query('SELECT admin_user, admin_pass FROM mikhmon_config WHERE id = 1');
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            return array('user' => $r['admin_user'], 'pass' => $r['admin_pass']);
        }
        return array();
    } catch (PDOException $e) {
        return array();
    }
}

function mikhmon_admin_set($user, $pass)
{
    $db = mikhmon_db();
    if ($db === false) {
        return false;
    }
    try {
        if ($db->query('SELECT COUNT(*) FROM mikhmon_config WHERE id = 1')->fetchColumn()) {
            return $db->prepare('UPDATE mikhmon_config SET admin_user = ?, admin_pass = ? WHERE id = 1')
                ->execute(array($user, $pass));
        }
        return $db->prepare('INSERT INTO mikhmon_config (id, admin_user, admin_pass) VALUES (1, ?, ?)')
            ->execute(array($user, $pass));
    } catch (PDOException $e) {
        return false;
    }
}

function mikhmon_sales_insert($row)
{
    $db = mikhmon_db();
    if ($db === false) {
        return 0;
    }
    $allowed = array('session', 'ros_script_id', 'name', 'source', 'owner', 'comment',
        'c_date', 'c_time', 'c_username', 'c_price', 'c_address', 'c_mac', 'c_validity',
        'c_profile', 'c_comment');
    $row = array_intersect_key($row, array_flip($allowed));
    try {
        // Dedupe on script id or record name: the page-load sync inserts
        // records without a script id, the importer inserts them with one.
        $sid = !empty($row['ros_script_id']) ? (string)$row['ros_script_id'] : '';
        $rname = isset($row['name']) ? (string)$row['name'] : '';
        if (!empty($row['session']) && ($sid !== '' || $rname !== '')) {
            $conds = array();
            $vals = array($row['session']);
            if ($sid !== '') {
                $conds[] = 'ros_script_id = ?';
                $vals[] = $sid;
            }
            if ($rname !== '') {
                $conds[] = 'name = ?';
                $vals[] = $rname;
            }
            $st = $db->prepare('SELECT id, ros_script_id FROM sales_records WHERE session = ? AND ('
                . implode(' OR ', $conds) . ') LIMIT 1');
            $st->execute($vals);
            $ex = $st->fetch(PDO::FETCH_ASSOC);
            if ($ex) {
                if ($sid !== '' && empty($ex['ros_script_id'])) {
                    // Learned the router id for a row inserted by the sync;
                    // return the id so --delete can clean up the script.
                    $db->prepare('UPDATE sales_records SET ros_script_id = ? WHERE id = ?')
                        ->execute(array($sid, $ex['id']));
                    return (int)$ex['id'];
                }
                return 0;
            }
        }
        $st = $db->prepare('INSERT INTO sales_records (' . implode(',', $allowed)
            . ') VALUES (' . implode(',', array_fill(0, count($allowed), '?')) . ')');
        $vals = array();
        foreach ($allowed as $k) {
            $vals[] = isset($row[$k]) ? $row[$k] : '';
        }
        $st->execute($vals);
        return (int)$db->lastInsertId();
    } catch (PDOException $e) {
        return 0;
    }
}

function mikhmon_sales_date_variants($d)
{
    $d = trim((string)$d);
    if ($d === '') {
        return array();
    }
    $variants = array($d);
    $mnames = "janfebmaraprmayjunjulaugsepoctnovdec";
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $d, $m)) {
        $year = (int)$m[1];
        $mnum = (int)$m[2];
        $day = (int)$m[3];
        if ($mnum >= 1 && $mnum <= 12) {
            $mstr = substr($mnames, ($mnum - 1) * 3, 3);
            $variants[] = sprintf('%04d-%02d-%02d', $year, $mnum, $day);
            $variants[] = $mstr . '/' . sprintf('%02d', $day) . '/' . $year;
            $variants[] = $mstr . '/' . $day . '/' . $year;
        }
    } elseif (preg_match('/^([A-Za-z]{3})\/(\d{1,2})\/(\d{2,4})$/', $d, $m)) {
        $mstr = strtolower($m[1]);
        $pos = strpos($mnames, $mstr);
        $mnum = ($pos !== false) ? (int)($pos / 3) + 1 : 1;
        $day = (int)$m[2];
        $year = (int)$m[3];
        if ($year < 100) {
            $year += 2000;
        }
        $variants[] = sprintf('%04d-%02d-%02d', $year, $mnum, $day);
        $variants[] = $mstr . '/' . sprintf('%02d', $day) . '/' . $year;
        $variants[] = $mstr . '/' . $day . '/' . $year;
    }
    return array_values(array_unique($variants));
}

function mikhmon_sales_where($session, $source = null, $owner = null, $comment = null, $prefix = null)
{
    $where = 'session = ?';
    $vals = array((string)$session);
    if ($source !== null && $source !== '') {
        $dvars = mikhmon_sales_date_variants($source);
        if (!empty($dvars)) {
            $placeholders = implode(',', array_fill(0, count($dvars), '?'));
            $where .= ' AND (source IN (' . $placeholders . ') OR c_date IN (' . $placeholders . '))';
            foreach ($dvars as $dv) { $vals[] = $dv; }
            foreach ($dvars as $dv) { $vals[] = $dv; }
        } else {
            $where .= ' AND (source = ? OR c_date = ?)';
            $vals[] = (string)$source;
            $vals[] = (string)$source;
        }
    }
    if ($owner !== null && $owner !== '') {
        $where .= ' AND owner = ?';
        $vals[] = (string)$owner;
    }
    if ($comment !== null && $comment !== '') {
        $where .= ' AND comment = ?';
        $vals[] = (string)$comment;
    }
    if ($prefix !== null && $prefix !== '') {
        $where .= ' AND c_username LIKE ?';
        $vals[] = (string)$prefix . '%';
    }
    return array($where, $vals);
}

function mikhmon_sales_find($session, $source = null, $owner = null, $comment = null, $limit = null, $offset = 0, $prefix = null)
{
    $db = mikhmon_db();
    if ($db === false) {
        return array();
    }
    list($where, $vals) = mikhmon_sales_where($session, $source, $owner, $comment, $prefix);
    $sql = 'SELECT * FROM sales_records WHERE ' . $where . ' ORDER BY imported_at DESC, id DESC';
    if ($limit !== null) {
        $sql .= ' LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
    }
    try {
        $st = $db->prepare($sql);
        $st->execute($vals);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return array();
    }
}

function mikhmon_sales_count_for($session, $source = null, $owner = null, $comment = null, $prefix = null)
{
    $db = mikhmon_db();
    if ($db === false) {
        return 0;
    }
    list($where, $vals) = mikhmon_sales_where($session, $source, $owner, $comment, $prefix);
    try {
        $st = $db->prepare('SELECT COUNT(*) FROM sales_records WHERE ' . $where);
        $st->execute($vals);
        return (int)$st->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function mikhmon_sales_sum_for($session, $source = null, $owner = null, $comment = null, $prefix = null)
{
    $db = mikhmon_db();
    if ($db === false) {
        return 0.0;
    }
    list($where, $vals) = mikhmon_sales_where($session, $source, $owner, $comment, $prefix);
    try {
        $st = $db->prepare('SELECT COALESCE(SUM(c_price), 0) FROM sales_records WHERE ' . $where);
        $st->execute($vals);
        return (float)$st->fetchColumn();
    } catch (PDOException $e) {
        return 0.0;
    }
}

function mikhmon_sales_resume_daily($session, $idbl)
{
    $db = mikhmon_db();
    $result = array('summary' => '', 'total_count' => 0, 'total_sum' => 0.0, 'map' => array());
    if ($db === false) {
        return $result;
    }
    try {
        $st = $db->prepare('SELECT c_date, COUNT(*) c, COALESCE(SUM(c_price), 0) s FROM sales_records WHERE session = ? AND owner = ? GROUP BY c_date');
        $st->execute(array((string)$session, (string)$idbl));
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $summary = '';
        foreach ($rows as $r) {
            $d = (string)$r['c_date'];
            $c = (int)$r['c'];
            $s = (float)$r['s'];
            $result['total_count'] += $c;
            $result['total_sum'] += $s;
            $val = $c . '/' . $s;
            $summary .= $d . $s;
            $result['map'][$d] = $val;
            foreach (mikhmon_sales_date_variants($d) as $v) {
                $result['map'][$v] = $val;
            }
        }
        $result['summary'] = $summary;
        return $result;
    } catch (PDOException $e) {
        return $result;
    }
}

// Sales records for today (legacy "nov/19/2026" or RouterOS 7 "2026-11-19").
function mikhmon_sales_today($session)
{
    $db = mikhmon_db();
    if ($db === false) {
        return array('count' => 0, 'sum' => 0.0);
    }
    $legacy = strtolower(date("M")) . "/" . date("d") . "/" . date("Y");
    $v7 = date("Y-m-d");
    try {
        $st = $db->prepare('SELECT COUNT(*) c, COALESCE(SUM(c_price), 0) s FROM sales_records WHERE session = ? AND (c_date = ? OR c_date = ?)');
        $st->execute(array($session, $legacy, $v7));
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return array('count' => $r ? (int)$r['c'] : 0, 'sum' => $r ? (float)$r['s'] : 0.0);
    } catch (PDOException $e) {
        return array('count' => 0, 'sum' => 0.0);
    }
}

// KPI statistics for the report cards.
function mikhmon_sales_stats($session)
{
    $thisBucket = strtolower(date("M")) . date("Y");
    $prevTs = strtotime(date("Y-m-01") . " -1 month");
    $prevBucket = strtolower(date("M", $prevTs)) . date("Y", $prevTs);
    $today = mikhmon_sales_today($session);
    return array(
        'total_count' => mikhmon_sales_count_for($session),
        'total_sum' => mikhmon_sales_sum_for($session),
        'month_count' => mikhmon_sales_count_for($session, null, $thisBucket),
        'month_sum' => mikhmon_sales_sum_for($session, null, $thisBucket),
        'prev_sum' => mikhmon_sales_sum_for($session, null, $prevBucket),
        'today_count' => $today['count'],
        'today_sum' => $today['sum'],
        'month_bucket' => $thisBucket,
    );
}

// Number of sales records stored for a session.
function mikhmon_sales_count($session)
{
    $db = mikhmon_db();
    if ($db === false) {
        return 0;
    }
    try {
        $st = $db->prepare('SELECT COUNT(*) FROM sales_records WHERE session = ?');
        $st->execute(array($session));
        return (int)$st->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

// True when a record script name is already in the DB for this session.
function mikhmon_sales_find_by_name($session, $name)
{
    $db = mikhmon_db();
    if ($db === false) {
        return false;
    }
    try {
        $st = $db->prepare('SELECT id FROM sales_records WHERE session = ? AND name = ? LIMIT 1');
        $st->execute(array($session, $name));
        return $st->fetch(PDO::FETCH_ASSOC) ? true : false;
    } catch (PDOException $e) {
        return false;
    }
}

function mikhmon_sales_sum_by_owner($session, $owner)
{
    $db = mikhmon_db();
    if ($db === false) {
        return 0.0;
    }
    try {
        $st = $db->prepare('SELECT COALESCE(SUM(c_price), 0) AS total FROM sales_records WHERE session = ? AND owner = ?');
        $st->execute(array($session, $owner));
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ? (float)$r['total'] : 0.0;
    } catch (PDOException $e) {
        return 0.0;
    }
}

function mikhmon_sales_delete($session, $source = null, $owner = null)
{
    $db = mikhmon_db();
    if ($db === false) {
        return 0;
    }
    $where = 'session = ?';
    $vals = array($session);
    if ($source !== null) {
        $where .= ' AND source = ?';
        $vals[] = $source;
    }
    if ($owner !== null) {
        $where .= ' AND owner = ?';
        $vals[] = $owner;
    }
    try {
        return (int)$db->prepare('DELETE FROM sales_records WHERE ' . $where)->execute($vals);
    } catch (PDOException $e) {
        return 0;
    }
}
