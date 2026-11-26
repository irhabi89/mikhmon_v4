<?php

declare(strict_types=1);

namespace Mikhmon\Sync;

use Mikhmon\Router\RouterClientFactory;
use Mikhmon\Router\RouterException;
use Mikhmon\Storage\Database;
use Mikhmon\Storage\RouterRecordRepository;
use Mikhmon\Storage\SalesRepository;
use Mikhmon\Storage\SyncRunRepository;
use Mikhmon\Support\Config;
use Mikhmon\Support\Log;
use Mikhmon\Support\RecordParser;

/**
 * Orchestrates full/month scans, import and reconciliation.
 * The status endpoint only reads SQLite; router work happens in a worker or
 * in explicit scan_month chunks.
 */
final class SalesSyncService
{
    /** @var Database */
    private $db;

    /** @var SalesRepository */
    private $sales;

    /** @var RouterRecordRepository */
    private $records;

    /** @var SyncRunRepository */
    private $runs;

    /** @var Reconciler */
    private $reconciler;

    /** @var RouterClientFactory */
    private $factory;

    /** @var resource|null */
    private $lockHandle = null;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->sales = new SalesRepository($db);
        $this->records = new RouterRecordRepository($db);
        $this->runs = new SyncRunRepository($db);
        $this->reconciler = new Reconciler($db, $this->runs);
        $this->factory = new RouterClientFactory($db);
    }

    /**
     * @param array<string,mixed> $opts
     * @return array<string,mixed>
     */
    public function start(string $session, array $opts = array()): array
    {
        $this->assertSession($session);
        $active = $this->runs->active($session);
        if ($active !== null) {
            return $this->status($session);
        }

        $month = isset($opts['month']) && $opts['month'] !== '' ? (string)$opts['month'] : null;
        if ($month !== null && RecordParser::monthKeyFromBucket($month) !== null) {
            $month = (string)RecordParser::monthKeyFromBucket($month);
        }
        if ($month !== null && preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            throw new RouterException('invalid month');
        }
        $mode = $month !== null ? 'month' : 'full';
        $worker = $this->chooseWorker();
        $runId = $this->runs->create($session, $mode, $worker, -1);
        $deleteAfter = !empty($opts['delete_after_import']);
        $this->runs->update($runId, array(
            'delete_after_import' => $deleteAfter ? 1 : 0,
        ));
        if ($month !== null) {
            $this->runs->update($runId, array('month_key' => $month));
        }

        if ($worker === 'cli') {
            if ($this->spawnWorker($runId)) {
                $this->runs->update($runId, array('phase' => 'starting'));
            } else {
                $worker = 'web';
                $this->runs->update($runId, array('worker' => 'web', 'status' => 'scanning', 'phase' => 'scan'));
            }
        }

        if ($worker === 'web') {
            $this->runs->update($runId, array('worker' => 'web', 'status' => 'scanning', 'phase' => 'scan'));
        }

        return $this->status($session);
    }

    /**
     * Create a run and execute it synchronously (CLI / fastcgi fallback).
     *
     * @return array<string,mixed>
     */
    public function runNow(string $session, ?string $month = null): array
    {
        $this->assertSession($session);
        $active = $this->runs->active($session);
        if ($active !== null) {
            return $this->status($session);
        }
        if ($month !== null && preg_match('/^[a-z]{3}\d{4}$/', $month) === 1) {
            $month = (string)RecordParser::monthKeyFromBucket($month);
        }
        if ($month !== null && preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            throw new RouterException('invalid month');
        }
        $mode = $month !== null ? 'month' : 'full';
        $runId = $this->runs->create($session, $mode, 'cli', -1);
        if ($month !== null) {
            $this->runs->update($runId, array('month_key' => $month));
        }
        return $this->runWorker($runId);
    }

    /**
     * Run a queued run in the current process (CLI worker or fastcgi).
     *
     * @return array<string,mixed>
     */
    public function runWorker(int $runId): array
    {
        $run = $this->runs->find($runId);
        if ($run === null) {
            throw new RouterException('sync run not found');
        }
        $session = (string)$run['session'];
        if (!$this->acquireLock($session)) {
            throw new RouterException('another sync is already running for this session');
        }

        try {
            $this->runs->update($runId, array(
                'status' => 'scanning',
                'phase' => 'scan',
                'heartbeat_at' => date('Y-m-d H:i:s'),
            ));
            $client = $this->factory->forSession($session);
            $client->connect();
            $scanner = new Scanner($client, $this->records, $this->runs);
            $scan = $run['mode'] === 'month' && !empty($run['month_key'])
                ? $scanner->scanMonth($session, $runId, (string)$run['month_key'])
                : $scanner->scanFull($session, $runId);

            if ($this->runs->isCancelRequested($runId) || $scan->error === 'cancelled') {
                $this->runs->update($runId, array(
                    'status' => 'canceled',
                    'finished_at' => date('Y-m-d H:i:s'),
                    'error' => 'cancelled',
                ));
                return $this->status($session);
            }
            if (!$scan->ok) {
                $this->runs->update($runId, array(
                    'status' => 'failed',
                    'phase' => 'scan',
                    'error' => $scan->error !== '' ? $scan->error : 'scan failed',
                    'finished_at' => date('Y-m-d H:i:s'),
                ));
                return $this->status($session);
            }

            $importer = new Importer($this->db, $this->sales, $this->records, $this->runs);
            $importer->run($session, $runId);
            $this->runs->update($runId, array('status' => 'verifying', 'phase' => 'verify'));
            $result = $this->reconciler->reconcile($session);
            $this->reconciler->persist($runId, $session, $result);
            if (!empty($run['delete_after_import']) && $result['complete'] && (int)$result['missing'] === 0) {
                $this->cleanupImportedScripts($session, $runId, $client);
            }
            $this->runs->update($runId, array(
                'status' => 'done',
                'phase' => 'done',
                'finished_at' => date('Y-m-d H:i:s'),
                'error' => '',
            ));
            $client->close();
            return $this->status($session);
        } catch (\Throwable $e) {
            Log::error('sync worker failed', array('run' => $runId, 'session' => $session, 'error' => $e->getMessage()));
            $this->runs->update($runId, array(
                'status' => 'failed',
                'error' => $e->getMessage(),
                'finished_at' => date('Y-m-d H:i:s'),
            ));
            return $this->status($session);
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function status(string $session): array
    {
        $run = $this->runs->latest($session);
        $active = $run !== null && in_array((string)$run['status'], array('queued', 'scanning', 'importing', 'verifying'), true);
        $recon = $active ? $this->reconciler->light($session) : $this->reconciler->reconcile($session);
        $months = array();

        $worker = $run !== null ? (string)$run['worker'] : '';
        $mode = $run !== null ? (string)$run['mode'] : '';
        if ($worker === 'web' && $mode === 'full' && $run !== null && in_array((string)$run['status'], array('scanning', 'queued'), true)) {
            $months = $this->monthsToScan($session);
        }

        $available = $run !== null && (int)$run['expected_scripts'] >= 0 && (string)$run['status'] !== 'failed';
        if ($run !== null && (string)$run['error'] !== '' && stripos((string)$run['error'], 'connection') !== false) {
            $available = false;
        }

        $dbTotal = (int)$recon['db_records'];
        $source = $dbTotal > 0 ? 'database' : (($recon['router_records'] > 0) ? 'router' : 'none');

        return array(
            'ok' => true,
            'session' => $session,
            'source' => $source,
            'db' => array(
                'total' => $dbTotal,
                'matched' => (int)$recon['matched'],
                'missing' => (int)$recon['missing'],
                'extra' => $recon['extra'],
                'extra_valid' => (bool)$recon['extra_valid'],
                'duplicates' => (int)$recon['duplicates'],
                'invalid' => (int)$recon['invalid'],
                'pending' => (int)$recon['pending'],
                'imported' => (int)$recon['imported'],
            ),
            'router' => array(
                'available' => $available,
                'records' => (int)$recon['router_records'],
                'expected_scripts' => $run !== null ? (int)$run['expected_scripts'] : -1,
                'scanned_scripts' => $run !== null ? (int)$run['scanned_scripts'] : 0,
                'complete' => (bool)$recon['complete'],
            ),
            'coverage' => $recon['coverage'],
            'exact' => $recon['exact'],
            'unsynced' => isset($recon['unsynced']) ? (int)$recon['unsynced'] : (int)$recon['missing'],
            'unsynced_percent' => isset($recon['unsynced_percent']) ? $recon['unsynced_percent'] : null,
            'synced_percent' => isset($recon['synced_percent']) ? $recon['synced_percent'] : null,
            'archive_extra' => isset($recon['archive_extra']) ? $recon['archive_extra'] : null,
            'verified' => isset($recon['verified']) ? (bool)$recon['verified'] : (bool)$recon['complete'],
            'in_sync' => (bool)$recon['in_sync'],
            'empty' => (bool)$recon['empty'],
            'run' => $run,
            'months' => $months,
            'samples' => $recon['samples'],
        );
    }

    /**
     * Cheap page-load summary based on persisted run counters. It avoids the
     * multi-subquery reconciliation for every report page view; the AJAX
     * status endpoint still computes the exact current values.
     *
     * @return array<string,mixed>
     */
    public function summary(string $session): array
    {
        $latest = $this->runs->latest($session);
        $completeRun = $this->runs->lastCompleteScan($session);
        $run = $completeRun !== null ? $completeRun : $latest;
        $dbTotal = $this->sales->count($session);
        $snapshot = $this->records->count($session);
        $missing = $run !== null ? (int)$run['missing'] : 0;
        $extra = $run !== null ? (int)$run['extra'] : 0;
        $invalid = $run !== null ? (int)$run['invalid'] : 0;
        $complete = $run !== null && (int)$run['complete'] === 1;
        $matched = max(0, $snapshot - $missing - $invalid);
        $matched = min($matched, $dbTotal);
        // DB rows that are not in the current router snapshot are archive/extra.
        // Compute from the indexed DB count so a restored legacy table cannot
        // leave the page showing a stale 100%.
        $extra = $complete ? max(0, $dbTotal - $matched) : 0;
        $coverage = $snapshot > 0 ? round($matched / $snapshot * 100, 1) : null;
        $denom = $matched + $missing + $invalid + ($complete ? $extra : 0);
        $exact = $denom > 0 ? round($matched / $denom * 100, 1) : null;
        $unsynced = $missing;
        $unsyncedPercent = $complete && $snapshot > 0 ? round($unsynced / $snapshot * 100, 1) : null;
        $syncedPercent = $complete && $snapshot > 0 ? round($matched / $snapshot * 100, 1) : null;
        $available = $latest !== null && (int)$latest['expected_scripts'] >= 0 && (string)$latest['status'] !== 'failed';
        if ($latest !== null && (string)$latest['error'] !== '' && stripos((string)$latest['error'], 'connection') !== false) {
            $available = false;
        }
        return array(
            'ok' => true,
            'session' => $session,
            'source' => $dbTotal > 0 ? 'database' : ($snapshot > 0 ? 'router' : 'none'),
            'db' => array(
                'total' => $dbTotal,
                'matched' => $matched,
                'missing' => $missing,
                'extra' => $complete ? $extra : null,
                'extra_valid' => $complete,
                'duplicates' => $run !== null ? (int)$run['duplicates'] : 0,
                'invalid' => $invalid,
                'pending' => 0,
                'imported' => $matched,
            ),
            'router' => array(
                'available' => $available,
                'records' => $snapshot,
                'expected_scripts' => $latest !== null ? (int)$latest['expected_scripts'] : -1,
                'scanned_scripts' => $latest !== null ? (int)$latest['scanned_scripts'] : 0,
                'complete' => $complete,
            ),
            'coverage' => $coverage,
            'exact' => $exact,
            'unsynced' => $unsynced,
            'unsynced_percent' => $unsyncedPercent,
            'synced_percent' => $syncedPercent,
            'archive_extra' => $complete ? $extra : null,
            'verified' => $complete,
            'in_sync' => $complete && $snapshot > 0 && $missing === 0 && $extra === 0 && $invalid === 0,
            'empty' => $snapshot === 0 && $dbTotal === 0,
            'run' => $latest,
            'months' => array(),
            'samples' => array('missing' => array(), 'extra' => array(), 'invalid' => array()),
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function scanMonth(string $session, int $runId, string $monthKey): array
    {
        $run = $this->runs->find($runId);
        if ($run === null || (string)$run['session'] !== $session) {
            throw new RouterException('sync run not found');
        }
        $client = $this->factory->forSession($session);
        $client->connect();
        try {
            $scanner = new Scanner($client, $this->records, $this->runs);
            $result = $scanner->scanMonth($session, $runId, $monthKey);
            $this->runs->update($runId, array(
                'status' => 'scanning',
                'phase' => 'scan',
                'scanned_scripts' => $result->seen,
                'router_records' => $this->records->count($session),
                'invalid' => $result->invalid,
                'error' => $result->error,
                'heartbeat_at' => date('Y-m-d H:i:s'),
            ));
        } finally {
            $client->close();
        }
        return $this->status($session);
    }

    /**
     * @return array<string,mixed>
     */
    public function importChunk(string $session, int $runId): array
    {
        $importer = new Importer($this->db, $this->sales, $this->records, $this->runs);
        $importer->run($session, $runId);
        return $this->status($session);
    }

    /**
     * @return array<string,mixed>
     */
    public function verify(string $session, int $runId): array
    {
        $run = $this->runs->find($runId);
        if ($run === null) {
            throw new RouterException('sync run not found');
        }
        $result = $this->reconciler->reconcile($session);
        $this->reconciler->persist($runId, $session, $result);
        if (!empty($run['delete_after_import']) && $result['complete'] && (int)$result['missing'] === 0) {
            try {
                $client = $this->factory->forSession($session);
                $client->connect();
                $this->cleanupImportedScripts($session, $runId, $client);
                $client->close();
            } catch (\Throwable $e) {
                $this->runs->update($runId, array('delete_error' => $e->getMessage()));
            }
            $result = $this->reconciler->reconcile($session);
            $this->reconciler->persist($runId, $session, $result);
        }
        $this->runs->update($runId, array(
            'status' => 'done',
            'phase' => 'done',
            'finished_at' => date('Y-m-d H:i:s'),
        ));
        return $this->status($session);
    }

    public function cancel(string $session, int $runId): array
    {
        $this->runs->requestCancel($runId);
        $this->runs->update($runId, array('status' => 'canceled', 'finished_at' => date('Y-m-d H:i:s')));
        return $this->status($session);
    }

    /**
     * Explicit connection test; never called by status polling.
     *
     * @return array<string,mixed>
     */
    public function probe(string $session): array
    {
        try {
            $client = $this->factory->forSession($session);
            $client->connect();
            $count = $client->count('/system/script/print');
            $client->close();
            return array('ok' => true, 'scripts' => $count, 'error' => '');
        } catch (\Throwable $e) {
            return array('ok' => false, 'scripts' => -1, 'error' => $e->getMessage());
        }
    }

    /**
     * Delete local rows and/or router scripts for the current filter.
     * The router deletion is explicit and can be slow; it is never automatic.
     *
     * @param array<string,mixed> $filter
     * @return array{db:int,router:int,errors:array<int,string>}
     */
    /**
     * Delete only sales-record scripts that were successfully imported.
     * Never deletes arbitrary scripts. Returns the number removed.
     */
    private function cleanupImportedScripts(string $session, int $runId, $client): int
    {
        $deleted = 0;
        $errors = array();
        while (true) {
            $rows = $this->records->importedWithIds($session, 500);
            if (empty($rows)) {
                break;
            }
            $progress = false;
            foreach ($rows as $row) {
                $name = isset($row['name']) ? (string)$row['name'] : '';
                $comment = isset($row['script_comment']) ? (string)$row['script_comment'] : '';
                $rosId = isset($row['ros_script_id']) ? (string)$row['ros_script_id'] : '';
                if ($name === '' || !RecordParser::isSalesRecord($name, $comment)) {
                    continue;
                }
                if ($rosId === '') {
                    $errors[] = 'script id tidak tersedia: ' . $name;
                    continue;
                }
                try {
                    $response = $client->command('/system/script/remove', array('.id' => $rosId));
                    if (isset($response['!trap'])) {
                        $errors[] = 'gagal hapus ' . $name;
                        continue;
                    }
                    $this->records->deleteOne($session, $name);
                    $deleted++;
                    $progress = true;
                } catch (\Throwable $e) {
                    $errors[] = $name . ': ' . $e->getMessage();
                }
            }
            if (!$progress) {
                break;
            }
        }
        $this->runs->update($runId, array(
            'deleted_router' => $deleted,
            'delete_error' => empty($errors) ? '' : substr(implode('; ', array_slice($errors, 0, 10)), 0, 2000),
        ));
        if ($deleted > 0) {
            $this->runs->update($runId, array('router_records' => $this->records->count($session)));
        }
        return $deleted;
    }

    public function deleteData(string $session, array $filter, bool $deleteDb, bool $deleteRouter): array
    {
        $this->assertSession($session);
        $out = array('db' => 0, 'router' => 0, 'errors' => array());
        $normalized = $this->sales->normalizeFilter($filter);

        if ($deleteDb) {
            if ($normalized['date_from'] === null && $normalized['date_to'] === null
                && $normalized['month_key'] === null && $normalized['month_name'] === null
                && $normalized['year'] === null && $normalized['q'] === null
                && $normalized['username_prefix'] === null && $normalized['server'] === null) {
                $out['db'] = $this->sales->deleteAll($session);
            } else {
                $out['db'] = $this->sales->deleteFiltered($session, $normalized);
            }
        }

        if (!$deleteRouter) {
            return $out;
        }

        $client = $this->factory->forSession($session);
        $client->connect();
        try {
            $queries = array();
            $dateFrom = $normalized['date_from'];
            $dateTo = $normalized['date_to'];
            $dateKey = $dateFrom !== null && $dateTo !== null && $dateFrom === $dateTo ? $dateFrom : null;
            $monthKey = $normalized['month_key'];
            $monthName = $normalized['month_name'];
            $year = $normalized['year'];
            $serverName = $normalized['server'];
            if ($dateKey !== null) {
                $legacy = strtolower(date('M', strtotime($dateKey))) . '/' . date('d', strtotime($dateKey)) . '/' . date('Y', strtotime($dateKey));
                $queries[] = array('?<name' => RecordParser::prefixUpperBound($legacy), '?>name' => $legacy);
                $queries[] = array('?<name' => RecordParser::prefixUpperBound($dateKey), '?>name' => $dateKey);
            } elseif ($monthKey !== null) {
                $bucket = RecordParser::bucketFromMonthKey($monthKey);
                if ($bucket !== null) {
                    $legacy = substr($bucket, 0, 3) . '/';
                    $queries[] = array('?<name' => RecordParser::prefixUpperBound($legacy), '?>name' => $legacy);
                }
                $queries[] = array('?<name' => RecordParser::prefixUpperBound($monthKey), '?>name' => $monthKey);
            } else {
                // A range/month-name/year/server filter is evaluated per row.
                $queries[] = array();
            }

            $ids = array();
            foreach ($queries as $q) {
                $result = $client->stream(
                    '/system/script/print',
                    array_merge(array('.proplist' => '.id,name,comment'), $q),
                    function (array $row) use (&$ids, $dateFrom, $dateTo, $dateKey, $monthKey, $monthName, $year, $serverName) {
                        $name = isset($row['name']) ? (string)$row['name'] : '';
                        $comment = isset($row['comment']) ? (string)$row['comment'] : '';
                        if ($name === '' || !RecordParser::isSalesRecord($name, $comment)) {
                            return true;
                        }
                        $parsed = RecordParser::parse($name);
                        if ($parsed === null) {
                            return true;
                        }
                        if ($dateKey !== null && $parsed['date_key'] !== $dateKey) {
                            return true;
                        }
                        if ($dateFrom !== null && $parsed['date_key'] < $dateFrom) {
                            return true;
                        }
                        if ($dateTo !== null && $parsed['date_key'] > $dateTo) {
                            return true;
                        }
                        if ($monthKey !== null && $parsed['month_key'] !== $monthKey) {
                            return true;
                        }
                        if ($monthName !== null && substr((string)$parsed['date_key'], 5, 2) !== $monthName) {
                            return true;
                        }
                        if ($year !== null && substr((string)$parsed['date_key'], 0, 4) !== $year) {
                            return true;
                        }
                        if ($serverName !== null) {
                            if ($serverName === '__none__') {
                                if ((string)$parsed['server_name'] !== '') { return true; }
                            } elseif ((string)$parsed['server_name'] !== $serverName) {
                                return true;
                            }
                        }
                        if (!empty($row['.id'])) {
                            $ids[] = array((string)$row['.id'], $name);
                        }
                        return true;
                    },
                    300.0
                );
                if (!$result->ok && $result->error !== '') {
                    $out['errors'][] = $result->error;
                }
            }

            foreach ($ids as $pair) {
                try {
                    $response = $client->command('/system/script/remove', array('.id' => $pair[0]));
                    if (!isset($response['!trap'])) {
                        $out['router']++;
                        $this->records->markDeleted($session, $pair[1]);
                    }
                } catch (\Throwable $e) {
                    $out['errors'][] = $e->getMessage();
                }
            }
        } finally {
            $client->close();
        }
        return $out;
    }

    /**
     * @return array<int,string>
     */
    public function monthsToScan(string $session): array
    {
        $lookback = max(1, Config::int('MIKHMON_SYNC_LOOKBACK_MONTHS', 36));
        $min = $this->db->value('SELECT MIN(month_key) FROM sales_records WHERE session = ?', array($session));
        $start = new \DateTimeImmutable(date('Y-m-01'));
        if (is_string($min) && preg_match('/^\d{4}-\d{2}$/', $min) === 1) {
            $dbStart = new \DateTimeImmutable($min . '-01');
            if ($dbStart < $start) {
                $start = $dbStart;
            }
        }
        $limit = new \DateTimeImmutable('-' . $lookback . ' months');
        if ($limit > $start) {
            $start = $limit;
        }
        $end = new \DateTimeImmutable(date('Y-m-01'));
        $months = array();
        $cursor = $start;
        while ($cursor <= $end) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->modify('+1 month');
        }
        return $months;
    }

    private function assertSession(string $session): void
    {
        $row = $this->db->fetchOne('SELECT name FROM mikhmon_sessions WHERE name = ?', array($session));
        if ($row === null) {
            throw new RouterException('router session not found');
        }
    }

    private function chooseWorker(): string
    {
        $mode = strtolower((string)Config::get('MIKHMON_SYNC_WORKER', 'auto'));
        if ($mode === 'web') {
            return 'web';
        }
        if (($mode === 'cli' || $mode === 'auto') && $this->canSpawn()) {
            return 'cli';
        }
        return 'web';
    }

    private function canSpawn(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        return !in_array('exec', $disabled, true);
    }

    private function spawnWorker(int $runId): bool
    {
        $bin = Config::baseDir() . '/bin/mikhmon';
        if (!is_file($bin)) {
            return false;
        }
        $log = Config::logDir() . '/sync-run-' . $runId . '.log';
        $php = (string)Config::get('MIKHMON_PHP_CLI', '');
        if ($php === '' || !is_executable($php)) {
            $candidate = PHP_BINDIR . '/php';
            $php = is_executable($candidate) ? $candidate : PHP_BINARY;
        }
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($bin) . ' sync:worker --run-id=' . (int)$runId
            . ' >> ' . escapeshellarg($log) . ' 2>&1 &';
        $output = array();
        $code = 0;
        @exec($cmd, $output, $code);
        return $code === 0;
    }

    private function acquireLock(string $session): bool
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $session);
        $dir = Config::baseDir() . '/data/locks';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $handle = @fopen($dir . '/sync-' . $safe . '.lock', 'c');
        if ($handle === false) {
            return false;
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }
        $this->lockHandle = $handle;
        return true;
    }

    private function releaseLock(): void
    {
        if (is_resource($this->lockHandle)) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
        }
        $this->lockHandle = null;
    }
}
