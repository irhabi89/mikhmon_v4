<?php

declare(strict_types=1);

namespace Mikhmon\Sync;

use Mikhmon\Storage\Database;
use Mikhmon\Storage\RouterRecordRepository;
use Mikhmon\Storage\SalesRepository;
use Mikhmon\Storage\SyncRunRepository;
use Mikhmon\Support\Config;
use Mikhmon\Support\RecordParser;

/**
 * Converts snapshot rows into sales_records in local batches.
 * No router connection is made here.
 */
final class Importer
{
    /** @var Database */
    private $db;

    /** @var SalesRepository */
    private $sales;

    /** @var RouterRecordRepository */
    private $records;

    /** @var SyncRunRepository */
    private $runs;

    /** @var int */
    private $batch;

    public function __construct(
        Database $db,
        SalesRepository $sales,
        RouterRecordRepository $records,
        SyncRunRepository $runs,
        ?int $batch = null
    ) {
        $this->db = $db;
        $this->sales = $sales;
        $this->records = $records;
        $this->runs = $runs;
        $this->batch = $batch !== null ? max(50, min(5000, $batch)) : max(50, Config::int('MIKHMON_SYNC_BATCH', 500));
    }

    /**
     * Import until the pending queue is empty (or cancelled).
     *
     * @return array{inserted:int,existing:int,invalid:int,failed:int,imported:int,cancelled:bool}
     */
    public function run(string $session, int $runId, ?callable $progress = null): array
    {
        $this->runs->update($runId, array(
            'status' => 'importing',
            'phase' => 'import',
            'heartbeat_at' => date('Y-m-d H:i:s'),
        ));

        $inserted = 0;
        $existing = 0;
        $invalid = 0;
        $failed = 0;
        $cancelled = false;

        while (true) {
            $pending = $this->records->pendingBatch($session, $this->batch);
            if (empty($pending)) {
                break;
            }

            $importedNames = array();
            $invalidNames = array();
            $this->db->transaction(function () use (
                $pending,
                $session,
                &$inserted,
                &$existing,
                &$invalid,
                &$failed,
                &$importedNames,
                &$invalidNames
            ) {
                foreach ($pending as $row) {
                    $name = (string)$row['name'];
                    $parsed = RecordParser::parse($name);
                    if ($parsed === null) {
                        $invalid++;
                        $invalidNames[] = $name;
                        continue;
                    }
                    $rosId = isset($row['ros_script_id']) ? (string)$row['ros_script_id'] : '';
                    $scriptComment = isset($row['script_comment']) ? (string)$row['script_comment'] : '';
                    try {
                        $didInsert = $this->sales->insertParsed($session, $name, $parsed, $rosId, $scriptComment);
                        if ($didInsert) {
                            $inserted++;
                        } else {
                            $existing++;
                        }
                        $importedNames[] = $name;
                    } catch (\Throwable $e) {
                        $failed++;
                        $invalidNames[] = $name;
                    }
                }
            });

            if (!empty($importedNames)) {
                $this->records->bulkMarkImported($session, $importedNames);
            }
            if (!empty($invalidNames)) {
                $this->records->bulkMarkInvalid($session, $invalidNames, 'unparseable or failed import');
            }

            $imported = $this->records->countState($session, 'imported');
            $this->runs->update($runId, array(
                'imported' => $imported,
                'heartbeat_at' => date('Y-m-d H:i:s'),
            ));
            if ($progress !== null) {
                call_user_func($progress, array(
                    'inserted' => $inserted,
                    'existing' => $existing,
                    'invalid' => $invalid,
                    'failed' => $failed,
                    'imported' => $imported,
                ));
            }
            if ($this->runs->isCancelRequested($runId)) {
                $cancelled = true;
                break;
            }
        }

        return array(
            'inserted' => $inserted,
            'existing' => $existing,
            'invalid' => $invalid,
            'failed' => $failed,
            'imported' => $this->records->countState($session, 'imported'),
            'cancelled' => $cancelled,
        );
    }
}
