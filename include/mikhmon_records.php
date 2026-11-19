<?php
/*
 * Mikhmon v3 "sale record" helpers.
 *
 * Sales records are /system/script entries with comment=mikhmon, created by
 * the user profile on-login script (see include/mikhmon_scripts.php). A record
 * name encodes the sale in "-|-"-separated columns:
 *
 *   [0] date    (legacy "nov/19/2026" or RouterOS 7 "2026-11-19")
 *   [1] time
 *   [2] user name
 *   [3] price
 *   [4] address
 *   [5] mac address
 *   [6] validity
 *   [7] profile name
 *   [8] comment
 *
 * The script "owner" is a month bucket ("nov2026"). RouterOS 7.24+ uses
 * owner=*sys for scripts created by the internal hotspot daemon, so
 * ?owner=<month> filters return nothing there.
 */

// Return the 9 parsed columns of a record script name (indexes 0..8,
// compatible with the explode("-|-", $name) reads used by the pages).
function mikhmon_script_parse($name)
{
    $cols = explode("-|-", (string)$name);
    $cols = array_slice($cols, 0, 9, true);
    for ($i = 0; $i < 9; $i++) {
        if (!isset($cols[$i])) {
            $cols[$i] = '';
        }
    }
    return $cols;
}

// Month bucket ("nov2026") of a record from its date column, or "" when
// the date cannot be parsed.
function mikhmon_script_month($name)
{
    $d = trim((string)mikhmon_script_parse((string)$name)[0]);
    if ($d === '') {
        return '';
    }
    if (preg_match('/^(\d{2,4})-(\d{1,2})-(\d{1,2})/', $d, $m)) {
        // RouterOS 7 style date, e.g. "2026-11-19"
        $ts = @strtotime(sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]));
    } elseif (preg_match('/^([A-Za-z]{3})\/(\d{1,2})\/(\d{2,4})$/', $d, $m)) {
        // Legacy Mikhmon date, e.g. "nov/19/2026"
        $mnum = (int)(strpos("janfebmaraprmayjunjulaugsepoctnovdec", strtolower($m[1])) / 3) + 1;
        $year = (int)$m[3];
        if ($year < 100) {
            $year += 2000;
        }
        $ts = @strtotime(sprintf('%04d-%02d-%02d', $year, $mnum, (int)$m[2]));
    } else {
        return '';
    }
    return ($ts === false) ? '' : strtolower(date("M", $ts)) . date("Y", $ts);
}

// Count of record rows in a /system/script/print result, 0 on error/trap.
function mikhmon_count_rows($rows)
{
    if (!is_array($rows) || isset($rows['!trap'])) {
        return 0;
    }
    return count($rows);
}

// RouterOS 7.24+ sets owner=*sys when a record script is added by the
// internal hotspot daemon; move those records to $cur_month (default:
// the current month bucket). Returns the number of records rewritten.
function mikhmon_scripts_normalize_owner($API, $cur_month = null)
{
    $cur_month = ($cur_month === null) ? strtolower(date("M")) . date("Y") : $cur_month;
    $res = $API->comm("/system/script/print", array(
        "?comment" => "mikhmon",
        "?owner" => "*sys",
        ".proplist" => ".id",
    ));
    if (!is_array($res) || isset($res['!trap']) || isset($res['!marker'])) {
        return 0;
    }
    $n = 0;
    foreach ($res as $sr) {
        if (!isset($sr['.id'])) {
            continue;
        }
        $set = $API->comm("/system/script/set", array(
            ".id" => $sr['.id'],
            "owner" => $cur_month,
        ));
        if (!is_array($set) || !isset($set['!trap'])) {
            $n++;
        }
    }
    return $n;
}

// All record scripts (comment=mikhmon). With the default single-prop
// proplist the rows carry only the given prop (memory-friendly on large
// routers); pass null for full rows (e.g. for imports).
function mikhmon_scripts_all($API, $proplist = 'name')
{
    $arr = array("?comment" => "mikhmon");
    if ($proplist !== null) {
        $arr[".proplist"] = $proplist;
    }
    $res = $API->comm("/system/script/print", $arr);
    if (!is_array($res) || isset($res['!trap']) || isset($res['!marker'])) {
        return array();
    }
    $rows = array();
    foreach ($res as $sr) {
        if (isset($sr['.id']) || ($proplist !== null && isset($sr['name']))) {
            $rows[] = $sr;
        }
    }
    return $rows;
}

// Filter record rows (from mikhmon_scripts_all) by month bucket ($idbl).
function mikhmon_scripts_by_month($rows, $idbl)
{
    $out = array();
    foreach ($rows as $r) {
        if (isset($r['name']) && mikhmon_script_month((string)$r['name']) === (string)$idbl) {
            $out[] = $r;
        }
    }
    return $out;
}

// Filter record rows by source date ($idhr, e.g. "nov/19/2026").
function mikhmon_scripts_by_source($rows, $idhr)
{
    $out = array();
    foreach ($rows as $r) {
        if (isset($r['name']) && mikhmon_script_parse((string)$r['name'])[0] === (string)$idhr) {
            $out[] = $r;
        }
    }
    return $out;
}

// New sales are recorded on the router via profile on-login scripts;
// fetch the records with a light proplist (single-prop proplist is the
// memory-friendly form that works on the app's PHP memory limit) and
// insert new ones into sales_records. Already-imported records
// (same script name) are skipped.
function mikhmon_sales_sync($API, $session)
{
    if (!function_exists('mikhmon_sales_insert')) {
        require_once __DIR__ . '/../lib/mikhmon_db.php';
        require_once __DIR__ . '/../lib/sessions_store.php';
    }
    $inserted = 0;
    $rows = $API->comm("/system/script/print", array("?comment" => "mikhmon", ".proplist" => "name"));
    foreach ($rows as $r) {
        if (!isset($r['name'])) {
            continue;
        }
        $name = (string)$r['name'];
        if (mikhmon_sales_find_by_name((string)$session, $name)) {
            continue;
        }
        $fields = mikhmon_script_parse($name);
        $id = mikhmon_sales_insert(array(
            'session' => (string)$session,
            'ros_script_id' => '',
            'name' => $name,
            'source' => $fields[0],
            'owner' => mikhmon_script_month($name),
            'comment' => 'mikhmon',
            'c_date' => $fields[0],
            'c_time' => $fields[1],
            'c_username' => $fields[2],
            'c_price' => $fields[3],
            'c_address' => $fields[4],
            'c_mac' => $fields[5],
            'c_validity' => $fields[6],
            'c_profile' => $fields[7],
            'c_comment' => $fields[8],
        ));
        if ($id > 0) {
            $inserted++;
        }
    }
    return $inserted;
}

// Sum of the price column (index 3) of record rows.
function mikhmon_scripts_sum_price($rows)
{
    $total = 0;
    foreach ($rows as $r) {
        if (isset($r['name'])) {
            $total += (float)mikhmon_script_parse((string)$r['name'])[3];
        }
    }
    return $total;
}
