<?php
/*
 * CLI sales sync.
 *
 * Usage:
 *   bin/mikhmon sync:run --session=livebarokah
 *   php migrations/import_sales.php --session=livebarokah [--month=nov2026] [--json]
 *   php migrations/import_sales.php --all-sessions
 *
 * The old per-row importer is replaced by the streamed snapshot/import pipeline.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
require_once dirname(__DIR__) . '/include/bootstrap.php';

$options = getopt('', array('session:', 'month::', 'all-sessions', 'json', 'help'));
if (isset($options['help'])) {
    echo "Usage: php migrations/import_sales.php --session=NAME [--month=MMMYYYY] [--json]\n";
    echo "       php migrations/import_sales.php --all-sessions\n";
    exit(0);
}

$service = new \Mikhmon\Sync\SalesSyncService(mikhmon_database());
$sessions = array();
if (!empty($options['session'])) {
    $sessions[] = (string)$options['session'];
} elseif (isset($options['all-sessions'])) {
    $rows = mikhmon_database()->fetchAll('SELECT name FROM mikhmon_sessions ORDER BY name');
    foreach ($rows as $row) {
        $sessions[] = (string)$row['name'];
    }
} else {
    fwrite(STDERR, "Use --session=NAME or --all-sessions\n");
    exit(2);
}

$exit = 0;
foreach ($sessions as $session) {
    try {
        $status = $service->runNow($session, isset($options['month']) ? (string)$options['month'] : null);
        if (isset($options['json'])) {
            echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
            continue;
        }
        printf(
            "%s: router=%d db=%d matched=%d missing=%d extra=%s invalid=%d coverage=%s exact=%s in_sync=%s\n",
            $session,
            (int)$status['router']['records'],
            (int)$status['db']['total'],
            (int)$status['db']['matched'],
            (int)$status['db']['missing'],
            $status['db']['extra'] === null ? 'n/a' : (string)$status['db']['extra'],
            (int)$status['db']['invalid'],
            $status['coverage'] === null ? 'n/a' : $status['coverage'] . '%',
            $status['exact'] === null ? 'n/a' : $status['exact'] . '%',
            $status['in_sync'] ? 'yes' : 'no'
        );
    } catch (Throwable $e) {
        fwrite(STDERR, $session . ': ' . $e->getMessage() . "\n");
        $exit = 1;
    }
}
exit($exit);
