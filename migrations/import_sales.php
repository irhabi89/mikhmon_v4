<?php
/*
 * Mikhmon v3 Phase 5: import sales records ("vcr") from router scripts.
 *
 * Sales are stored on the Mikrotik as /system/script entries with
 * comment=mikhmon; the record name encodes the sale ("-|-" separated):
 *   date-|-time-|-user-|-price-|-address-|-mac-|-validity-|-profile-|-comment
 * (see include/mikhmon_records.php). The script `owner` is a month bucket
 * ("nov2026"); RouterOS 7.24+ uses owner=*sys.
 *
 * Usage:
 *   php migrations/import_sales.php [--dry-run] [--delete] [--month MMMMYYYY]
 *
 *   --dry-run         report counts, write nothing (read-only; use for
 *                     verifying the import before the real run).
 *   --delete          after a successful import, remove the imported scripts
 *                     from that router ("data no longer on Mikrotik").
 *   --month MMMMYYYY  only import records for one month (e.g. nov2026);
 *                     run the script repeatedly per month for step-by-step
 *                     migration of routers with many records (>50k).
 *
 * For large routers (50k+ records) use a higher PHP memory limit, e.g.
 *   php -d memory_limit=2G migrations/import_sales.php
 */
require_once dirname(__DIR__) . '/lib/mikhmon_db.php';
require_once dirname(__DIR__) . '/lib/sessions_store.php';
require_once dirname(__DIR__) . '/lib/routeros_api.class.php';
require_once dirname(__DIR__) . '/include/mikhmon_records.php';

$opts = array('dry_run' => false, 'delete' => false, 'month' => null);
$argv = $_SERVER['argv'] ?? array();
for ($i = 1; $i < count($argv); $i++) {
    if ($argv[$i] === '--dry-run') {
        $opts['dry_run'] = true;
    } elseif ($argv[$i] === '--delete') {
        $opts['delete'] = true;
    } elseif ($argv[$i] === '--month' && isset($argv[$i + 1])) {
        $opts['month'] = $argv[++$i];
    }
}

function mikhmon_import_sales_session($session, $opts)
{
    $row = mikhmon_session_get((string)$session);
    if (!is_array($row)) {
        echo "$session: session missing from DB (run migrations/import_config.php first)\n";
        return 0;
    }
    $API = new RouterosAPI();
    $API->debug = false;
    if (!$API->connect($row['ip_host'], $row['user_host'], decrypt($row['pass_host']))) {
        echo "$session: connect FAILED\n";
        return 0;
    }
    $scripts = $API->comm("/system/script/print", array("?comment" => "mikhmon"));
    $total = mikhmon_count_rows($scripts);
    $imported = 0;
    $deleted = 0;
    $importedIds = array();
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
            'source' => isset($s['source']) ? (string)$s['source'] : '',
            // Computed month bucket ("nov2026"); RouterOS 7.24+ uses owner=*sys on the router.
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
        if ($opts['dry_run']) {
            continue;
        }
        $id = mikhmon_sales_insert($record);
        if ($id > 0) {
            $imported++;
            if (!empty($record['ros_script_id'])) {
                $importedIds[] = $record['ros_script_id'];
            }
        }
    }
    if ($opts['delete'] && !empty($importedIds)) {
        foreach ($importedIds as $rid) {
            $r = $API->comm("/system/script/remove", array('.id' => $rid));
            if (!is_array($r) || !isset($r['!trap'])) {
                $deleted++;
            }
        }
    }
    printf("%s: total=%d imported=%d deleted=%d%s\n",
        $session, $total, $imported, $deleted,
        $opts['month'] !== null ? " (month=" . $opts['month'] . ")" : "");
    $API->disconnect();
    return $imported;
}

$t0 = microtime(true);
$totalImported = 0;
foreach (mikhmon_sessions_all() as $session) {
    $totalImported += mikhmon_import_sales_session($session, $opts);
}
printf("import_sales: total_imported=%d (%.1fs)\n", $totalImported, microtime(true) - $t0);
