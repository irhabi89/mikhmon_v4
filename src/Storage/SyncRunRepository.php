<?php

declare(strict_types=1);

namespace Mikhmon\Storage;

/**
 * Persisted sync run state. The report page/status endpoint reads this table
 * only; it never opens a router connection just to show progress.
 */
final class SyncRunRepository
{
    /** @var Database */
    private $db;

    /** @var array<int,string> */
    private static $allowed = array(
        'status', 'phase', 'worker', 'month_key', 'expected_scripts', 'scanned_scripts',
        'router_records', 'imported', 'missing', 'extra', 'duplicates',
        'invalid', 'complete', 'cancel_requested', 'error', 'heartbeat_at',
        'finished_at', 'started_at',
    );

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function create(string $session, string $mode, string $worker, int $expected = -1): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO sales_sync_runs
                (session, mode, status, phase, worker, expected_scripts, started_at, heartbeat_at)
             VALUES (?,?,?,?,?,?,?,?)',
            array($session, $mode, 'queued', 'queued', $worker, $expected, $now, $now)
        );
        return (int)$this->db->pdo()->lastInsertId();
    }

    /**
     * @param array<string,mixed> $fields
     */
    public function update(int $id, array $fields): void
    {
        $set = array();
        $params = array();
        foreach ($fields as $key => $value) {
            if (!in_array($key, self::$allowed, true)) {
                continue;
            }
            $set[] = $key . ' = ?';
            $params[] = $value;
        }
        if (empty($set)) {
            return;
        }
        $params[] = $id;
        $this->db->execute('UPDATE sales_sync_runs SET ' . implode(', ', $set) . ' WHERE id = ?', $params);
    }

    public function heartbeat(int $id): void
    {
        $this->update($id, array('heartbeat_at' => date('Y-m-d H:i:s')));
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM sales_sync_runs WHERE id = ?', array($id));
    }

    public function latest(string $session): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM sales_sync_runs WHERE session = ? ORDER BY id DESC LIMIT 1',
            array($session)
        );
    }

    public function active(string $session): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM sales_sync_runs
             WHERE session = ? AND status IN ('queued','scanning','importing','verifying')
             ORDER BY id DESC LIMIT 1",
            array($session)
        );
    }

    public function lastCompleted(string $session): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM sales_sync_runs
             WHERE session = ? AND status = 'done' AND complete = 1
             ORDER BY id DESC LIMIT 1",
            array($session)
        );
    }

    public function lastCompleteScan(string $session): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM sales_sync_runs
             WHERE session = ? AND complete = 1
             ORDER BY id DESC LIMIT 1',
            array($session)
        );
    }

    public function requestCancel(int $id): void
    {
        $this->update($id, array('cancel_requested' => 1));
    }

    public function isCancelRequested(int $id): bool
    {
        $value = $this->db->value('SELECT cancel_requested FROM sales_sync_runs WHERE id = ?', array($id));
        return (int)$value === 1;
    }

    public function markStalled(int $id): void
    {
        $this->update($id, array(
            'status' => 'stalled',
            'error' => 'worker heartbeat timeout',
            'finished_at' => date('Y-m-d H:i:s'),
        ));
    }

    public function markOrphaned(string $session): void
    {
        $this->db->execute(
            "UPDATE sales_sync_runs SET status = 'failed', error = 'interrupted before start', finished_at = ?
             WHERE session = ? AND status = 'queued' AND started_at < ?",
            array(date('Y-m-d H:i:s'), $session, date('Y-m-d H:i:s', time() - 120))
        );
    }
}
