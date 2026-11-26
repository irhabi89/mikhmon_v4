<?php

declare(strict_types=1);

namespace Mikhmon\Sync;

use Mikhmon\Storage\Database;
use Mikhmon\Storage\SyncRunRepository;

/**
 * Exact set reconciliation between the router snapshot and SQLite.
 * Counts alone are not enough: a DB with different records of the same size
 * must never look "100% synced".
 */
final class Reconciler
{
    /** @var Database */
    private $db;

    /** @var SyncRunRepository */
    private $runs;

    public function __construct(Database $db, SyncRunRepository $runs)
    {
        $this->db = $db;
        $this->runs = $runs;
    }

    /**
     * @return array<string,mixed>
     */
    public function reconcile(string $session): array
    {
        // Completeness of the snapshot is proven by a full scan, not by a
        // later partial/month repair run.
        $run = $this->runs->lastCompleteScan($session);
        $complete = $run !== null && (int)$run['complete'] === 1;

        $row = $this->db->fetchOne(
            'SELECT
                (SELECT COUNT(*) FROM sales_router_records WHERE session = ?) AS router_records,
                (SELECT COUNT(*) FROM sales_records WHERE session = ?) AS db_records,
                (SELECT COUNT(*)
                   FROM sales_router_records r
                   JOIN sales_records d ON d.session = r.session AND d.name = r.name
                  WHERE r.session = ?) AS matched,
                (SELECT COUNT(*)
                   FROM sales_router_records r
                  WHERE r.session = ?
                    AND NOT EXISTS (SELECT 1 FROM sales_records d WHERE d.session = r.session AND d.name = r.name)) AS missing,
                (SELECT COUNT(*)
                   FROM sales_records d
                  WHERE d.session = ?
                    AND NOT EXISTS (SELECT 1 FROM sales_router_records r WHERE r.session = d.session AND r.name = d.name)) AS extra,
                (SELECT COALESCE(SUM(CASE WHEN dup_count > 1 THEN dup_count - 1 ELSE 0 END),0)
                   FROM sales_router_records WHERE session = ?) AS duplicates,
                (SELECT COUNT(*) FROM sales_router_records WHERE session = ? AND state = \'invalid\') AS invalid,
                (SELECT COUNT(*) FROM sales_router_records WHERE session = ? AND state = \'pending\') AS pending,
                (SELECT COUNT(*) FROM sales_router_records WHERE session = ? AND state = \'imported\') AS imported',
            array($session, $session, $session, $session, $session, $session, $session, $session, $session)
        );
        if (!is_array($row)) {
            $row = array();
        }

        $router = (int)(isset($row['router_records']) ? $row['router_records'] : 0);
        $dbCount = (int)(isset($row['db_records']) ? $row['db_records'] : 0);
        $matched = (int)(isset($row['matched']) ? $row['matched'] : 0);
        $missing = (int)(isset($row['missing']) ? $row['missing'] : 0);
        $extra = $complete ? (int)(isset($row['extra']) ? $row['extra'] : 0) : null;
        $duplicates = (int)(isset($row['duplicates']) ? $row['duplicates'] : 0);
        $invalid = (int)(isset($row['invalid']) ? $row['invalid'] : 0);
        $pending = (int)(isset($row['pending']) ? $row['pending'] : 0);
        $imported = (int)(isset($row['imported']) ? $row['imported'] : 0);

        $coverage = $router > 0 ? round($matched / $router * 100, 1) : null;
        $denominator = $matched + $missing + $invalid + ($extra === null ? 0 : $extra);
        $exact = $denominator > 0 ? round($matched / $denominator * 100, 1) : null;
        $unsynced = $missing;
        $unsyncedPercent = $complete && $router > 0 ? round($unsynced / $router * 100, 1) : null;
        $syncedPercent = $complete && $router > 0 ? round($matched / $router * 100, 1) : null;
        $inSync = $complete && $router > 0 && $missing === 0 && $extra === 0 && $invalid === 0;

        return array(
            'run' => $run,
            'complete' => $complete,
            'empty' => $router === 0 && $dbCount === 0,
            'router_records' => $router,
            'db_records' => $dbCount,
            'matched' => $matched,
            'missing' => $missing,
            'extra' => $extra,
            'extra_valid' => $extra !== null,
            'duplicates' => $duplicates,
            'invalid' => $invalid,
            'pending' => $pending,
            'imported' => $imported,
            'coverage' => $coverage,
            'exact' => $exact,
            'unsynced' => $unsynced,
            'unsynced_percent' => $unsyncedPercent,
            'synced_percent' => $syncedPercent,
            'archive_extra' => $extra,
            'verified' => $complete,
            'in_sync' => $inSync,
            'samples' => array(
                'missing' => $this->sample('sales_router_records', $session, true),
                'extra' => $extra !== null ? $this->sample('sales_records', $session, false) : array(),
                'invalid' => $this->db->fetchAll(
                    'SELECT name FROM sales_router_records WHERE session = ? AND state = \'invalid\' ORDER BY name LIMIT 10',
                    array($session)
                ),
            ),
        );
    }

    /**
     * Fast progress view used while a scan/import is running. It relies on
     * indexed counts instead of set-difference subqueries.
     *
     * @return array<string,mixed>
     */
    public function light(string $session): array
    {
        $run = $this->runs->lastCompleteScan($session);
        $complete = $run !== null && (int)$run['complete'] === 1;
        $snapshot = (int)$this->db->value('SELECT COUNT(*) FROM sales_router_records WHERE session = ?', array($session));
        $dbCount = (int)$this->db->value('SELECT COUNT(*) FROM sales_records WHERE session = ?', array($session));
        $pending = (int)$this->db->value('SELECT COUNT(*) FROM sales_router_records WHERE session = ? AND state = \'pending\'', array($session));
        $invalid = (int)$this->db->value('SELECT COUNT(*) FROM sales_router_records WHERE session = ? AND state = \'invalid\'', array($session));
        $missing = $pending;
        $matched = max(0, $snapshot - $pending - $invalid);
        $coverage = $snapshot > 0 ? round($matched / $snapshot * 100, 1) : null;
        $denom = $matched + $missing + $invalid;
        $exact = $denom > 0 ? round($matched / $denom * 100, 1) : null;
        $unsynced = $missing + $invalid;
        $unsyncedPercent = $snapshot > 0 ? round($unsynced / $snapshot * 100, 1) : null;
        $syncedPercent = $snapshot > 0 ? round($matched / $snapshot * 100, 1) : null;

        return array(
            'run' => $run,
            'complete' => $complete,
            'empty' => $snapshot === 0 && $dbCount === 0,
            'router_records' => $snapshot,
            'db_records' => $dbCount,
            'matched' => $matched,
            'missing' => $missing,
            'extra' => null,
            'extra_valid' => false,
            'duplicates' => 0,
            'invalid' => $invalid,
            'pending' => $pending,
            'imported' => max(0, $snapshot - $pending - $invalid),
            'coverage' => $coverage,
            'exact' => $exact,
            'unsynced' => $unsynced,
            'unsynced_percent' => $unsyncedPercent,
            'synced_percent' => $syncedPercent,
            'archive_extra' => null,
            'verified' => false,
            'in_sync' => false,
            'samples' => array('missing' => array(), 'extra' => array(), 'invalid' => array()),
        );
    }

    /**
     * @param array<string,mixed> $result
     */
    public function persist(int $runId, string $session, array $result): void
    {
        $this->runs->update($runId, array(
            'router_records' => $result['router_records'],
            'missing' => $result['missing'],
            'extra' => $result['extra'] === null ? 0 : $result['extra'],
            'duplicates' => $result['duplicates'],
            'invalid' => $result['invalid'],
            'complete' => $result['complete'] ? 1 : 0,
        ));
    }

    /**
     * @return array<int,string>
     */
    private function sample(string $table, string $session, bool $routerSide): array
    {
        if ($routerSide) {
            $rows = $this->db->fetchAll(
                'SELECT name FROM sales_router_records r
                 WHERE r.session = ?
                   AND NOT EXISTS (SELECT 1 FROM sales_records d WHERE d.session = r.session AND d.name = r.name)
                 ORDER BY r.name LIMIT 10',
                array($session)
            );
        } else {
            $rows = $this->db->fetchAll(
                'SELECT name FROM sales_records d
                 WHERE d.session = ?
                   AND NOT EXISTS (SELECT 1 FROM sales_router_records r WHERE r.session = d.session AND r.name = d.name)
                 ORDER BY d.name LIMIT 10',
                array($session)
            );
        }
        $out = array();
        foreach ($rows as $row) {
            $out[] = (string)$row['name'];
        }
        return $out;
    }
}
