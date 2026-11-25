<?php
declare(strict_types=1);
namespace Mikhmon\Tests;

use Mikhmon\Router\RouterClientInterface;
use Mikhmon\Storage\Database;
use Mikhmon\Storage\Migrator;
use Mikhmon\Storage\RouterRecordRepository;
use Mikhmon\Storage\SalesRepository;
use Mikhmon\Storage\SyncRunRepository;
use Mikhmon\Sync\Importer;
use Mikhmon\Sync\Reconciler;
use Mikhmon\Sync\ScanResult;
use Mikhmon\Sync\Scanner;
use PHPUnit\Framework\TestCase;

final class FakeRouterClient implements RouterClientInterface
{
    /** @var array<int,array<string,mixed>> */
    private $rows;
    private $connected = false;

    public function __construct(array $rows) { $this->rows = $rows; }
    public function connect(): void { $this->connected = true; }
    public function count(string $command, array $queries = array()): int { return count($this->rows); }
    public function stream(string $command, array $attributes, callable $onRow, ?float $timeout = null): ScanResult
    {
        $result = new ScanResult();
        $n = 0;
        foreach ($this->rows as $row) {
            $n++;
            if ($onRow($row) === false) { $result->ok = false; $result->error = 'cancelled'; break; }
        }
        $result->seen = $n;
        return $result;
    }
    public function command(string $command, array $attributes = array()): array { return array(); }
    public function close(): void { $this->connected = false; }
    public function isConnected(): bool { return $this->connected; }
    public function lastError(): string { return ''; }
}

final class SyncPipelineTest extends TestCase
{
    private $path;
    private $db;

    protected function setUp(): void
    {
        $this->path = __DIR__ . '/tmp/test-' . bin2hex(random_bytes(4)) . '.db';
        $this->db = new Database($this->path);
        (new Migrator($this->db))->migrate();
        $this->db->execute(
            'INSERT INTO mikhmon_sessions (name, ip_host, port_host, user_host, pass_host, hotspot_name) VALUES (?,?,?,?,?,?)',
            array('s1', '127.0.0.1', 8728, 'admin', '', 'test')
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        @unlink($this->path . '-wal');
        @unlink($this->path . '-shm');
    }

    private function rows(int $n, string $prefix = 'user'): array
    {
        $rows = array();
        for ($i = 0; $i < $n; $i++) {
            $day = 1 + ($i % 28);
            $rows[] = array(
                '.id' => '*' . $i,
                'name' => sprintf('nov/%02d/2026-|-10:00:00-|-%s%d-|-5000-|-addr-|-AA:BB:CC:DD:EE:%02d-|-30d-|-1mb-|-ok', $day, $prefix, $i, $i % 100),
                'comment' => 'mikhmon',
            );
        }
        return $rows;
    }

    public function testFullPipelineAtScale(): void
    {
        $runs = new SyncRunRepository($this->db);
        $records = new RouterRecordRepository($this->db);
        $sales = new SalesRepository($this->db);
        $runId = $runs->create('s1', 'full', 'cli', -1);
        $scanner = new Scanner(new FakeRouterClient($this->rows(5000)), $records, $runs, 500);
        $result = $scanner->scanFull('s1', $runId);
        $this->assertTrue($result->complete);
        $this->assertSame(5000, $result->seen);

        $importer = new Importer($this->db, $sales, $records, $runs, 500);
        $imported = $importer->run('s1', $runId);
        $this->assertSame(5000, $imported['inserted']);
        $this->assertSame(5000, $sales->count('s1'));

        $reconcile = (new Reconciler($this->db, $runs))->reconcile('s1');
        $this->assertTrue($reconcile['in_sync']);
        $this->assertSame(100.0, $reconcile['exact']);
    }

    public function testSameCountButDifferentRecordsIsNotOneHundredPercent(): void
    {
        $runs = new SyncRunRepository($this->db);
        $records = new RouterRecordRepository($this->db);
        $sales = new SalesRepository($this->db);
        $runId = $runs->create('s1', 'full', 'cli', -1);
        $scanner = new Scanner(new FakeRouterClient($this->rows(500, 'router')), $records, $runs, 500);
        $scanner->scanFull('s1', $runId);

        // 500 different DB records of the same size.
        foreach ($this->rows(500, 'db') as $row) {
            $parsed = \Mikhmon\Support\RecordParser::parse((string)$row['name']);
            $sales->insertParsed('s1', (string)$row['name'], $parsed, '', 'mikhmon');
        }

        $reconcile = (new Reconciler($this->db, $runs))->reconcile('s1');
        $this->assertSame(0, $reconcile['matched']);
        $this->assertSame(500, $reconcile['missing']);
        $this->assertSame(500, $reconcile['extra']);
        $this->assertSame(0.0, (float)$reconcile['exact']);
        $this->assertFalse($reconcile['in_sync']);
    }
}
