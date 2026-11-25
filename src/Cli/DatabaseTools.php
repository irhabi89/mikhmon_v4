<?php

declare(strict_types=1);

namespace Mikhmon\Cli;

use Mikhmon\Storage\Database;

final class DatabaseTools
{
    public static function backup(Database $db): string
    {
        $dir = dirname($db->path()) . '/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $target = $dir . '/mikhmon-' . date('Ymd-His') . '.db';
        $pdo = $db->pdo();
        try {
            $pdo->exec('VACUUM INTO ' . $pdo->quote($target));
        } catch (\Throwable $e) {
            $db->checkpoint();
            if (!@copy($db->path(), $target)) {
                throw new \RuntimeException('backup failed: ' . $e->getMessage());
            }
        }
        return $target;
    }

    /**
     * @return array<string,mixed>
     */
    public static function check(Database $db): array
    {
        $pdo = $db->pdo();
        return array(
            'path' => $db->path(),
            'size' => is_file($db->path()) ? filesize($db->path()) : 0,
            'integrity' => $pdo->query('PRAGMA integrity_check')->fetchColumn(),
            'page_count' => (int)$pdo->query('PRAGMA page_count')->fetchColumn(),
            'free_pages' => (int)$pdo->query('PRAGMA freelist_count')->fetchColumn(),
            'sales' => (int)$pdo->query('SELECT COUNT(*) FROM sales_records')->fetchColumn(),
            'snapshot' => (int)$pdo->query('SELECT COUNT(*) FROM sales_router_records')->fetchColumn(),
            'runs' => (int)$pdo->query('SELECT COUNT(*) FROM sales_sync_runs')->fetchColumn(),
        );
    }

    public static function resetLegacyQueue(Database $db): void
    {
        $pdo = $db->pdo();
        $table = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='sales_import_queue_legacy_v1'")->fetchColumn();
        if ($table !== false && $table !== null) {
            $pdo->exec('DELETE FROM sales_import_queue_legacy_v1');
        }
    }
}
