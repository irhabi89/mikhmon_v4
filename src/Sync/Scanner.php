<?php

declare(strict_types=1);

namespace Mikhmon\Sync;

use Mikhmon\Router\RouterClientInterface;
use Mikhmon\Router\RouterException;
use Mikhmon\Storage\RouterRecordRepository;
use Mikhmon\Storage\SyncRunRepository;
use Mikhmon\Support\Config;
use Mikhmon\Support\RecordParser;

/**
 * Reads /system/script over the API and writes a local snapshot.
 *
 * Important: the callback receives one !re row at a time, so memory stays
 * bounded even for 100k+ scripts. The router is read completely; filtering is
 * done locally so a missing/renamed "comment=mikhmon" does not hide records.
 */
final class Scanner
{
    /** @var RouterClientInterface */
    private $client;

    /** @var RouterRecordRepository */
    private $records;

    /** @var SyncRunRepository */
    private $runs;

    /** @var int */
    private $batch;

    public function __construct(
        RouterClientInterface $client,
        RouterRecordRepository $records,
        SyncRunRepository $runs,
        ?int $batch = null
    ) {
        $this->client = $client;
        $this->records = $records;
        $this->runs = $runs;
        $this->batch = $batch !== null ? max(50, min(5000, $batch)) : max(50, Config::int('MIKHMON_SYNC_BATCH', 500));
    }

    /**
     * Full scan of every script on the router.
     */
    public function scanFull(string $session, int $runId, ?callable $progress = null): ScanResult
    {
        $expected = $this->client->count('/system/script/print');
        if ($expected < 0) {
            throw new RouterException('cannot count router scripts: ' . $this->client->lastError());
        }

        $this->runs->update($runId, array('expected_scripts' => $expected));

        $result = $this->scanCommand(
            $session,
            $runId,
            '/system/script/print',
            array(),
            array('.proplist' => '.id,name,comment'),
            $expected,
            $progress
        );

        $complete = $result->ok && $result->seen >= $expected;
        if ($complete) {
            // The snapshot is a complete view at this point: drop rows that
            // disappeared from the router. DB rows are left as archive/extra.
            $this->records->deleteNotSeen($session, $runId);
        }

        $this->runs->update($runId, array(
            'expected_scripts' => $expected,
            'scanned_scripts' => $result->seen,
            'router_records' => $this->records->count($session),
            'invalid' => $result->invalid,
            'complete' => $complete ? 1 : 0,
            'heartbeat_at' => date('Y-m-d H:i:s'),
            'error' => $result->error,
        ));

        $result->expected = $expected;
        $result->complete = $complete;
        $result->records = $this->records->count($session);
        return $result;
    }

    /**
     * Scan the records of one calendar month with API range queries.
     * Used by the chunked web fallback and by repair runs.
     */
    public function scanMonth(string $session, int $runId, string $monthKey, ?callable $progress = null): ScanResult
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $monthKey, $m) !== 1) {
            throw new RouterException('invalid month: ' . $monthKey);
        }
        $legacy = RecordParser::bucketFromMonthKey($monthKey);
        if ($legacy === null) {
            throw new RouterException('invalid month bucket: ' . $monthKey);
        }
        $prefixes = array(
            substr($legacy, 0, 3) . '/', // legacy "nov/"
            $monthKey,                   // ROS7 "2026-11"
        );

        $totalExpected = 0;
        $totalSeen = 0;
        $totalRecords = 0;
        $totalInvalid = 0;
        $ok = true;
        $error = '';

        foreach ($prefixes as $prefix) {
            $queries = array(
                '?<name' => RecordParser::prefixUpperBound($prefix),
                '?>name' => $prefix,
            );
            $expected = $this->client->count('/system/script/print', $queries);
            if ($expected < 0) {
                $ok = false;
                $error = 'cannot count month range: ' . $this->client->lastError();
                continue;
            }
            $totalExpected += $expected;
            $part = $this->scanCommand(
                $session,
                $runId,
                '/system/script/print',
                $queries,
                array('.proplist' => '.id,name,comment'),
                $expected,
                $progress
            );
            $totalSeen += $part->seen;
            $totalRecords += $part->records;
            $totalInvalid += $part->invalid;
            if (!$part->ok || $part->seen < $expected) {
                $ok = false;
                $error = $part->error !== '' ? $part->error : 'month range incomplete';
            }
        }

        $result = new ScanResult();
        $result->ok = $ok;
        $result->expected = $totalExpected;
        $result->seen = $totalSeen;
        $result->records = $totalRecords;
        $result->invalid = $totalInvalid;
        $result->error = $error;
        $result->complete = false; // a month scan alone is not a full-router proof

        $this->runs->update($runId, array(
            'expected_scripts' => $totalExpected,
            'scanned_scripts' => $totalSeen,
            'router_records' => $this->records->count($session),
            'invalid' => $totalInvalid,
            'heartbeat_at' => date('Y-m-d H:i:s'),
            'error' => $error,
        ));
        return $result;
    }

    /**
     * @param array<string,mixed> $queries
     * @param array<string,mixed> $attributes
     */
    private function scanCommand(
        string $session,
        int $runId,
        string $command,
        array $queries,
        array $attributes,
        int $expected,
        ?callable $progress
    ): ScanResult {
        $this->runs->update($runId, array(
            'status' => 'scanning',
            'phase' => 'scan',
            'heartbeat_at' => date('Y-m-d H:i:s'),
        ));

        $batch = array();
        $seen = 0;
        $records = 0;
        $invalid = 0;
        $cancelled = false;
        $flush = function () use (&$batch, $runId, &$seen, &$records, &$invalid) {
            if (empty($batch)) {
                return;
            }
            $this->records->upsertMany($batch, $runId);
            $batch = array();
            $this->runs->update($runId, array(
                'scanned_scripts' => $seen,
                'router_records' => $records,
                'invalid' => $invalid,
                'heartbeat_at' => date('Y-m-d H:i:s'),
            ));
        };

        $result = $this->client->stream(
            $command,
            array_merge($attributes, $queries),
            function (array $row) use ($session, &$batch, &$seen, &$records, &$invalid, &$cancelled, $flush, $runId, $progress) {
                $seen++;
                $name = isset($row['name']) ? (string)$row['name'] : '';
                if ($name === '') {
                    return true;
                }
                $comment = isset($row['comment']) ? (string)$row['comment'] : '';
                if (!RecordParser::isSalesRecord($name, $comment)) {
                    return true;
                }
                $rosId = isset($row['.id']) ? (string)$row['.id'] : '';
                $parsed = RecordParser::parse($name);
                if ($parsed === null) {
                    $invalid++;
                    $batch[] = array($session, $name, $rosId, $comment, 'invalid', 'unparseable sales record name');
                } else {
                    $records++;
                    $batch[] = array($session, $name, $rosId, $comment, 'pending', '');
                }

                if (count($batch) >= $this->batch) {
                    $flush();
                }
                if ($progress !== null) {
                    call_user_func($progress, array(
                        'seen' => $seen,
                        'records' => $records,
                        'invalid' => $invalid,
                    ));
                }
                if (($seen % 1000) === 0 && $this->runs->isCancelRequested($runId)) {
                    $cancelled = true;
                    return false;
                }
                return true;
            },
            300.0
        );

        $flush();

        $out = new ScanResult();
        $out->ok = $result->ok && !$cancelled;
        $out->expected = $expected;
        $out->seen = $seen;
        $out->records = $records;
        $out->invalid = $invalid;
        $out->complete = false;
        $out->error = $cancelled ? 'cancelled' : $result->error;
        return $out;
    }
}
