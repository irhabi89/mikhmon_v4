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

    public function testServerFilterAndStats(): void
    {
        $sales = new SalesRepository($this->db);
        $rows = array(
            array('2026-11-19-|-10:00-|-u1-|-5000-|-a-|-m-|-1d-|-p-|-c|#MMSRV1#|hs-a', 'hs-a'),
            array('2026-11-19-|-10:01-|-u2-|-7000-|-a-|-m-|-1d-|-p-|-c|#MMSRV1#|hs-b', 'hs-b'),
            array('2026-11-19-|-10:02-|-u3-|-3000-|-a-|-m-|-1d-|-p-|-c', ''),
        );
        foreach ($rows as $row) {
            $parsed = \Mikhmon\Support\RecordParser::parse($row[0]);
            $this->assertSame($row[1], $parsed['server_name']);
            $sales->insertParsed('s1', $row[0], $parsed, '', 'mikhmon');
        }
        $this->assertSame(array('', 'hs-a', 'hs-b'), $sales->serverNames('s1'));
        $this->assertSame(1, $sales->countFiltered('s1', array('server' => 'hs-a')));
        $this->assertSame(3, $sales->countFiltered('s1', array('month_name' => '11', 'year' => '2026')));
        $this->assertSame(0, $sales->countFiltered('s1', array('month_name' => '10')));
        $this->assertSame(1, $sales->countFiltered('s1', array('server' => '__none__')));
        $stats = $sales->serverStats('s1');
        $this->assertCount(3, $stats);
        $byName = array();
        foreach ($stats as $stat) { $byName[$stat['server_name']] = $stat; }
        $this->assertSame(1, $byName['hs-a']['total_count']);
        $this->assertSame(1, $byName['__none__']['total_count']);
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
    public function testExactUnsyncedMetricsAndArchive(): void
    {
        $runs = new SyncRunRepository($this->db);
        $records = new RouterRecordRepository($this->db);
        $sales = new SalesRepository($this->db);
        $runId = $runs->create('s1', 'full', 'web', 3);
        $runs->update($runId, array('status' => 'done', 'complete' => 1, 'router_records' => 3, 'missing' => 1, 'invalid' => 0, 'extra' => 0));
        $rows = $this->rows(3);
        $snapshot = array();
        foreach ($rows as $row) {
            $parsed = \Mikhmon\Support\RecordParser::parse($row['name']);
            $snapshot[] = array('s1', $row['name'], $row['.id'], 'mikhmon', (string)($parsed['server_name'] ?? ''), 'pending', '');
        }
        $records->upsertMany($snapshot, $runId);
        foreach (array_slice($rows, 0, 2) as $row) {
            $parsed = \Mikhmon\Support\RecordParser::parse($row['name']);
            $sales->insertParsed('s1', $row['name'], $parsed, $row['.id'], 'mikhmon');
        }
        $recon = (new Reconciler($this->db, $runs))->reconcile('s1');
        $this->assertSame(1, $recon['unsynced']);
        $this->assertSame(33.3, $recon['unsynced_percent']);
        $this->assertTrue($recon['verified']);
        $this->assertSame(0, $recon['archive_extra']);

        $extra = \Mikhmon\Support\RecordParser::parse('nov/20/2026-|-10:00:00-|-archive-|-5000-|-addr-|-AA:BB:CC:DD:EE:99-|-30d-|-1mb-|-ok');
        $sales->insertParsed('s1', 'nov/20/2026-|-10:00:00-|-archive-|-5000-|-addr-|-AA:BB:CC:DD:EE:99-|-30d-|-1mb-|-ok', $extra, '', 'mikhmon');
        $recon = (new Reconciler($this->db, $runs))->reconcile('s1');
        $this->assertSame(1, $recon['archive_extra']);
        $this->assertSame(33.3, $recon['unsynced_percent']);
    }

    public function testCleanupDeletesOnlyImportedSalesRecords(): void
    {
        $records = new RouterRecordRepository($this->db);
        $valid = $this->rows(1)[0]['name'];
        $records->upsertMany(array(
            array('s1', $valid, '*valid', 'mikhmon', 'hotspot1', 'imported', ''),
            array('s1', 'system backup script', '*other', '', 'hotspot1', 'imported', ''),
        ), 1);
        $cleaner = new \Mikhmon\Sync\ImportedSalesCleaner($records, new FakeRouterClient(array()));
        $result = $cleaner->cleanup('s1');
        $this->assertSame(1, $result['deleted']);
        $this->assertNull($records->find('s1', $valid));
        $this->assertNotNull($records->find('s1', 'system backup script'));
    }

}
