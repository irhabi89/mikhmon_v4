<?php
/*
 * Mikhmon v3 Phase 5: import sales records ("vcr") from router scripts.
 *
 * The report page has an Import panel for everyday use; this CLI is for
 * batch/step-by-step migration (e.g. one month at a time for routers with
 * tens of thousands of records).
 *
 * Usage:
 *   php migrations/import_sales.php [--dry-run] [--delete] [--month MMMMYYYY]
 *
 *   --dry-run         report counts, write nothing (read-only).
 *   --delete          after a successful import, remove the imported scripts
 *                     from that router. NOT needed for day-to-day use: if
 *                     omitted, the router keeps recording and the app can
 *                     import the new records later.
 *   --month MMMMYYYY  only import records for one month (e.g. nov2026).
 *
 * For large routers (50k+ records) use a higher PHP memory limit, e.g.
 *   php -d memory_limit=2G migrations/import_sales.php
 */
require_once dirname(__DIR__) . '/lib/sales_import.php';

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

$t0 = microtime(true);
$totalImported = 0;
foreach (mikhmon_sessions_all() as $session) {
    $res = mikhmon_import_sales_session($session, $opts);
    if (empty($res['ok'])) {
        echo "$session: " . $res['error'] . "\n";
        continue;
    }
    printf("%s: total=%d imported=%d deleted=%d%s\n",
        $session, $res['total'], $res['imported'], $res['deleted'],
        $opts['month'] !== null ? " (month=" . $opts['month'] . ")" : "");
    $totalImported += $res['imported'];
}
printf("import_sales: total_imported=%d (%.1fs)\n", $totalImported, microtime(true) - $t0);
