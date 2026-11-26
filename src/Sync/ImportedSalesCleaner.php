<?php

declare(strict_types=1);

namespace Mikhmon\Sync;

use Mikhmon\Router\RouterClientInterface;
use Mikhmon\Storage\RouterRecordRepository;
use Mikhmon\Support\RecordParser;

/**
 * Removes only successful sales-record scripts after a manual import.
 * Non-sales scripts and failed/invalid records are never removed.
 */
final class ImportedSalesCleaner
{
    /** @var RouterRecordRepository */
    private $records;

    /** @var RouterClientInterface */
    private $client;

    public function __construct(RouterRecordRepository $records, RouterClientInterface $client)
    {
        $this->records = $records;
        $this->client = $client;
    }

    /**
     * @return array{deleted:int,errors:array<int,string>}
     */
    public function cleanup(string $session): array
    {
        $deleted = 0;
        $errors = array();
        $guard = 0;
        while ($guard++ < 200) {
            $rows = $this->records->importedWithIds($session, 500);
            if (empty($rows)) { break; }
            $progress = false;
            foreach ($rows as $row) {
                $name = isset($row['name']) ? (string)$row['name'] : '';
                $comment = isset($row['script_comment']) ? (string)$row['script_comment'] : '';
                $rosId = isset($row['ros_script_id']) ? (string)$row['ros_script_id'] : '';
                if ($name === '' || !RecordParser::isSalesRecord($name, $comment)) {
                    continue;
                }
                if ($rosId === '') {
                    $errors[] = 'script id tidak tersedia: ' . $name;
                    continue;
                }
                try {
                    $response = $this->client->command('/system/script/remove', array('.id' => $rosId));
                    if (isset($response['!trap'])) {
                        $errors[] = 'gagal hapus: ' . $name;
                        continue;
                    }
                    $this->records->deleteOne($session, $name);
                    $deleted++;
                    $progress = true;
                } catch (\Throwable $e) {
                    $errors[] = $name . ': ' . $e->getMessage();
                }
            }
            if (!$progress) { break; }
        }
        return array('deleted' => $deleted, 'errors' => $errors);
    }
}
