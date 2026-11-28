<?php

declare(strict_types=1);

namespace Mikhmon\Storage;

use PDO;
use PDOException;

/**
 * SQLite connection with WAL and sane busy/cache settings.
 */
final class Database
{
    /** @var PDO */
    private $pdo;

    /** @var string */
    private $path;

    /** @var array<string,\PDOStatement> */
    private $statements = array();

    public function __construct(string $path)
    {
        $this->path = $path;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $this->pdo = new PDO('sqlite:' . $path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA busy_timeout=10000');
        $mode = strtolower((string)$this->pdo->query('PRAGMA journal_mode')->fetchColumn());
        if ($mode !== 'wal') {
            $this->pdo->exec('PRAGMA journal_mode=WAL');
        }
        $this->pdo->exec('PRAGMA synchronous=NORMAL');
        $this->pdo->exec('PRAGMA temp_store=MEMORY');
        $this->pdo->exec('PRAGMA cache_size=-20000');
        $this->pdo->exec('PRAGMA foreign_keys=ON');
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @param array<int,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    public function fetchAll(string $sql, array $params = array()): array
    {
        $st = $this->statement($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        $st->closeCursor();
        return $rows;
    }

    /**
     * @param array<int,mixed> $params
     * @return array<string,mixed>|null
     */
    public function fetchOne(string $sql, array $params = array()): ?array
    {
        $st = $this->statement($sql);
        $st->execute($params);
        $row = $st->fetch();
        $st->closeCursor();
        return $row === false ? null : $row;
    }

    /**
     * @param array<int,mixed> $params
     * @return mixed
     */
    public function value(string $sql, array $params = array())
    {
        $st = $this->statement($sql);
        $st->execute($params);
        $val = $st->fetchColumn();
        $st->closeCursor();
        return $val;
    }

    /**
     * @param array<int,mixed> $params
     */
    public function execute(string $sql, array $params = array()): int
    {
        $st = $this->statement($sql);
        $st->execute($params);
        $count = $st->rowCount();
        $st->closeCursor();
        return $count;
    }

    private function statement(string $sql): \PDOStatement
    {
        if (!isset($this->statements[$sql])) {
            if (count($this->statements) > 80) {
                $this->statements = array();
            }
            $this->statements[$sql] = $this->pdo->prepare($sql);
        }
        return $this->statements[$sql];
    }

    /**
     * Run a callable inside a transaction. Commits or rolls back.
     *
     * @param callable $fn
     * @return mixed
     */
    public function transaction(callable $fn)
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this->pdo);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function isBusy(PDOException $e): bool
    {
        return strpos($e->getMessage(), 'database is locked') !== false
            || strpos($e->getMessage(), 'database table is locked') !== false;
    }

    public function vacuum(): void
    {
        $this->pdo->exec('VACUUM');
    }

    public function checkpoint(): void
    {
        $this->pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    }

    public function optimize(): void
    {
        $this->pdo->exec('PRAGMA optimize');
    }
}
