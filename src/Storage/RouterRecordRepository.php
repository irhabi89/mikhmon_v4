<?php

declare(strict_types=1);

namespace Mikhmon\Storage;

/**
 * Snapshot of all sales records currently present in the router.
 * Its state column doubles as a persistent import queue.
 */
final class RouterRecordRepository
{
    /** @var Database */
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Upsert a batch of scanned rows. Each row:
     * [session, name, ros_script_id, script_comment, state, error]
     *
     * @param array<int,array{0:string,1:string,2:string,3:string,4:string,5:string}> $rows
     */
    public function upsertMany(array $rows, int $runId, ?string $now = null): int
    {
        if (empty($rows)) {
            return 0;
        }
        if ($now === null) {
            $now = date('Y-m-d H:i:s');
        }
        $pdo = $this->db->pdo();
        $sql = <<<'SQL'
INSERT INTO sales_router_records
    (session, name, ros_script_id, script_comment, state, run_id,
     first_seen_at, last_seen_at, imported_at, dup_count, error)
VALUES (?,?,?,?,?,?,?,?,'',1,?)
ON CONFLICT(session, name) DO UPDATE SET
    ros_script_id = CASE WHEN excluded.ros_script_id != '' THEN excluded.ros_script_id ELSE sales_router_records.ros_script_id END,
    script_comment = excluded.script_comment,
    last_seen_at = excluded.last_seen_at,
    run_id = excluded.run_id,
    dup_count = CASE WHEN sales_router_records.run_id = excluded.run_id
                     THEN sales_router_records.dup_count + 1 ELSE 1 END,
    state = CASE
        WHEN sales_router_records.state = 'imported' THEN 'imported'
        WHEN excluded.state = 'invalid' THEN 'invalid'
        ELSE 'pending'
    END,
    error = CASE WHEN excluded.state = 'invalid' THEN excluded.error ELSE sales_router_records.error END
SQL;
        $st = $pdo->prepare($sql);
        $count = 0;
        $this->db->transaction(function () use ($st, $rows, $runId, $now, &$count) {
            foreach ($rows as $row) {
                $st->execute(array(
                    $row[0],
                    $row[1],
                    $row[2],
                    $row[3],
                    $row[4],
                    $runId,
                    $now,
                    $now,
                    $row[5],
                ));
                $count++;
            }
        });
        return $count;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function pendingBatch(string $session, int $limit): array
    {
        $limit = max(1, min(5000, $limit));
        return $this->db->fetchAll(
            "SELECT * FROM sales_router_records WHERE session = ? AND state = 'pending' LIMIT " . $limit,
            array($session)
        );
    }

    public function markImported(string $session, string $name, string $rosScriptId = ''): void
    {
        $this->db->execute(
            "UPDATE sales_router_records
             SET state = 'imported',
                 imported_at = ?,
                 error = '',
                 ros_script_id = CASE WHEN ? != '' THEN ? ELSE ros_script_id END
             WHERE session = ? AND name = ?",
            array(date('Y-m-d H:i:s'), $rosScriptId, $rosScriptId, $session, $name)
        );
    }

    /**
     * Mark a whole imported batch in one UPDATE (much faster than per row).
     *
     * @param array<int,string> $names
     */
    public function bulkMarkImported(string $session, array $names): void
    {
        if (empty($names)) {
            return;
        }
        $names = array_values(array_unique($names));
        foreach (array_chunk($names, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $params = array_merge(array(date('Y-m-d H:i:s'), $session), $chunk);
            $this->db->execute(
                "UPDATE sales_router_records SET state = 'imported', imported_at = ?, error = ''
                 WHERE session = ? AND name IN (" . $placeholders . ")",
                $params
            );
        }
    }

    /**
     * @param array<int,string> $names
     */
    public function bulkMarkInvalid(string $session, array $names, string $error): void
    {
        if (empty($names)) {
            return;
        }
        $names = array_values(array_unique($names));
        foreach (array_chunk($names, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $params = array_merge(array($error, $session), $chunk);
            $this->db->execute(
                "UPDATE sales_router_records SET state = 'invalid', error = ?
                 WHERE session = ? AND name IN (" . $placeholders . ")",
                $params
            );
        }
    }

    public function markInvalid(string $session, string $name, string $error): void
    {
        $this->db->execute(
            "UPDATE sales_router_records SET state = 'invalid', error = ? WHERE session = ? AND name = ?",
            array($error, $session, $name)
        );
    }

    public function markDeleted(string $session, string $name): void
    {
        $this->db->execute(
            "UPDATE sales_router_records SET state = 'deleted' WHERE session = ? AND name = ?",
            array($session, $name)
        );
    }

    public function deleteNotSeen(string $session, int $runId): int
    {
        return $this->db->execute(
            'DELETE FROM sales_router_records WHERE session = ? AND run_id < ?',
            array($session, $runId)
        );
    }

    public function count(string $session): int
    {
        return (int)$this->db->value(
            'SELECT COUNT(*) FROM sales_router_records WHERE session = ?',
            array($session)
        );
    }

    public function countState(string $session, string $state): int
    {
        return (int)$this->db->value(
            'SELECT COUNT(*) FROM sales_router_records WHERE session = ? AND state = ?',
            array($session, $state)
        );
    }

    public function pendingCount(string $session): int
    {
        return $this->countState($session, 'pending');
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function importedBatch(string $session, int $limit): array
    {
        $limit = max(1, min(5000, $limit));
        return $this->db->fetchAll(
            "SELECT * FROM sales_router_records
             WHERE session = ? AND state = 'imported'
             LIMIT " . $limit,
            array($session)
        );
    }

    /**
     * @return array<int,string>
     */
    public function sampleNames(string $session, string $state, int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));
        $rows = $this->db->fetchAll(
            'SELECT name FROM sales_router_records WHERE session = ? AND state = ? ORDER BY name LIMIT ' . $limit,
            array($session, $state)
        );
        $out = array();
        foreach ($rows as $row) {
            $out[] = (string)$row['name'];
        }
        return $out;
    }

    public function find(string $session, string $name): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM sales_router_records WHERE session = ? AND name = ?',
            array($session, $name)
        );
    }

    public function deleteBySession(string $session): int
    {
        return $this->db->execute('DELETE FROM sales_router_records WHERE session = ?', array($session));
    }
}
