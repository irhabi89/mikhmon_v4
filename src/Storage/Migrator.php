<?php

declare(strict_types=1);

namespace Mikhmon\Storage;

use Mikhmon\Router\RouterTarget;

/**
 * Baseline migrator for the new sales schema.
 *
 * The old sales tables are renamed, never dropped. The router scan is the
 * authoritative source for the new sales_records table.
 */
final class Migrator
{
    public const VERSION = 5;

    /** @var Database */
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function currentVersion(): int
    {
        $pdo = $this->db->pdo();
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version INTEGER PRIMARY KEY, applied_at TEXT NOT NULL)');
        $value = $pdo->query('SELECT MAX(version) FROM schema_migrations')->fetchColumn();
        return $value === false || $value === null ? 0 : (int)$value;
    }

    public function isCurrent(): bool
    {
        return $this->currentVersion() >= self::VERSION;
    }

    /**
     * @return array<int,string> applied migration notes
     */
    public function migrateIfNeeded(): array
    {
        if ($this->isCurrent()) {
            return array();
        }
        return $this->migrate();
    }

    /**
     * @return array<int,string>
     */
    public function migrate(): array
    {
        $pdo = $this->db->pdo();
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version INTEGER PRIMARY KEY, applied_at TEXT NOT NULL)');
        $notes = array();

        $version = $this->currentVersion();
        if ($version >= self::VERSION) {
            return $notes;
        }

        // 1. Park the old sales tables instead of destroying them.
        if ($version < 1 && $this->tableExists('sales_records') && !$this->columnExists('sales_records', 'date_key')) {
            $target = 'sales_records_legacy_v1';
            if ($this->tableExists($target)) {
                $target .= '_' . date('YmdHis');
            }
            $pdo->exec('ALTER TABLE "sales_records" RENAME TO "' . $target . '"');
            $notes[] = 'renamed sales_records -> ' . $target;
        }
        if ($version < 1 && $this->tableExists('sales_import_queue')) {
            $target = 'sales_import_queue_legacy_v1';
            if ($this->tableExists($target)) {
                $target .= '_' . date('YmdHis');
            }
            $pdo->exec('ALTER TABLE "sales_import_queue" RENAME TO "' . $target . '"');
            $notes[] = 'renamed sales_import_queue -> ' . $target;
        }

        // 2. Apply the baseline schema.
        if ($version < 1) {
            $pdo->exec($this->readSql('001_baseline.sql'));
            $notes[] = 'applied 001_baseline.sql';
        }

        // 3. Cover the report's default ORDER BY (date, time, id).
        if ($version < 2) {
            $pdo->exec($this->readSql('002_indexes.sql'));
            $notes[] = 'applied 002_indexes.sql';
        }

        // 4. Queue scans by (session, state) without a name sort.
        if ($version < 3) {
            $pdo->exec($this->readSql('003_queue_index.sql'));
            $notes[] = 'applied 003_queue_index.sql';
        }

        // 5. Per-server sales reporting.
        if ($version < 4) {
            if (!$this->columnExists('sales_records', 'server_name')) {
                $pdo->exec("ALTER TABLE sales_records ADD COLUMN server_name TEXT NOT NULL DEFAULT ''");
                $notes[] = 'added sales_records.server_name';
            }
            if (!$this->columnExists('sales_router_records', 'server_name')) {
                $pdo->exec("ALTER TABLE sales_router_records ADD COLUMN server_name TEXT NOT NULL DEFAULT ''");
                $notes[] = 'added sales_router_records.server_name';
            }
            $pdo->exec($this->readSql('004_server.sql'));
            $notes[] = 'applied 004_server.sql';
        }

        // 6. Manual import cleanup flags and on-login webhook settings.
        if ($version < 5) {
            $syncColumns = array(
                'delete_after_import' => "INTEGER NOT NULL DEFAULT 0",
                'deleted_router' => "INTEGER NOT NULL DEFAULT 0",
                'delete_error' => "TEXT NOT NULL DEFAULT ''",
            );
            foreach ($syncColumns as $column => $definition) {
                if (!$this->columnExists('sales_sync_runs', $column)) {
                    $pdo->exec('ALTER TABLE sales_sync_runs ADD COLUMN "' . $column . '" ' . $definition);
                    $notes[] = 'added sales_sync_runs.' . $column;
                }
            }
            $sessionColumns = array(
                'webhook_url' => "TEXT NOT NULL DEFAULT ''",
                'webhook_token' => "TEXT NOT NULL DEFAULT ''",
            );
            foreach ($sessionColumns as $column => $definition) {
                if (!$this->columnExists('mikhmon_sessions', $column)) {
                    $pdo->exec('ALTER TABLE mikhmon_sessions ADD COLUMN "' . $column . '" ' . $definition);
                    $notes[] = 'added mikhmon_sessions.' . $column;
                }
            }
            $pdo->exec($this->readSql('005_sync_webhook.sql'));
            $notes[] = 'applied 005_sync_webhook.sql';
        }

        // 3. Add port_host to existing sessions and split host:port values.
        $addedPort = false;
        if ($this->tableExists('mikhmon_sessions') && !$this->columnExists('mikhmon_sessions', 'port_host')) {
            $pdo->exec('ALTER TABLE mikhmon_sessions ADD COLUMN port_host INTEGER NOT NULL DEFAULT 8728');
            $addedPort = true;
            $notes[] = 'added mikhmon_sessions.port_host';
        }
        if ($this->tableExists('mikhmon_sessions')) {
            $this->migrateSessionTargets($addedPort);
            $notes[] = 'normalised session host/port';
        }

        $pdo->prepare('INSERT OR REPLACE INTO schema_migrations (version, applied_at) VALUES (?, ?)')
            ->execute(array(self::VERSION, date('Y-m-d H:i:s')));
        $pdo->exec('PRAGMA user_version=' . self::VERSION);

        return $notes;
    }

    private function readSql(string $file): string
    {
        $path = ConfigPath::migrationsDir() . '/' . $file;
        if (!is_file($path)) {
            throw new \RuntimeException('Migration file missing: ' . $path);
        }
        return (string)file_get_contents($path);
    }

    private function tableExists(string $table): bool
    {
        $st = $this->db->pdo()->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
        $st->execute(array($table));
        return $st->fetchColumn() !== false;
    }

    private function columnExists(string $table, string $column): bool
    {
        $table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
        $column = preg_replace('/[^A-Za-z0-9_]/', '', $column);
        $rows = $this->db->pdo()->query('PRAGMA table_info("' . $table . '")')->fetchAll();
        foreach ($rows as $row) {
            if (isset($row['name']) && (string)$row['name'] === $column) {
                return true;
            }
        }
        return false;
    }

    private function migrateSessionTargets(bool $onlyWhenAdded): void
    {
        $pdo = $this->db->pdo();
        $rows = $pdo->query('SELECT name, ip_host, port_host FROM mikhmon_sessions')->fetchAll();
        $update = $pdo->prepare('UPDATE mikhmon_sessions SET ip_host = ?, port_host = ? WHERE name = ?');
        foreach ($rows as $row) {
            $current = isset($row['port_host']) ? (int)$row['port_host'] : 8728;
            if (!$onlyWhenAdded && $current > 0 && strpos((string)$row['ip_host'], ':') === false) {
                continue;
            }
            $target = RouterTarget::fromString((string)$row['ip_host'], $current > 0 ? $current : 8728);
            $update->execute(array($target->host(), $target->port(), $row['name']));
        }
    }
}

/**
 * Small helper to keep the migration directory independent of Config.
 */
final class ConfigPath
{
    public static function migrationsDir(): string
    {
        return dirname(__DIR__, 2) . '/migrations';
    }
}
