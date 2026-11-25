<?php

declare(strict_types=1);

namespace Mikhmon\Cli;

use Mikhmon\Storage\SalesRepository;
use Mikhmon\Support\RecordParser;
use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Import the archived v1 sales_records table (e.g. data/mikhmon.db.backup)
 * into the new schema. Only sales_records is touched; the router snapshot and
 * sync runs are left alone.
 */
final class DbImportLegacyCommand extends Command
{
    protected static $defaultName = 'db:import-legacy';

    protected function configure(): void
    {
        $this->setDescription('Import sales rows from an old Mikhmon database/backup into the new schema')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Old SQLite file', 'data/mikhmon.db.backup')
            ->addOption('session', null, InputOption::VALUE_REQUIRED, 'Target session override')
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Batch size', '1000')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Stop after N source rows (testing)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Parse/report only, write nothing')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $from = (string)$input->getOption('from');
        if (!is_file($from)) {
            $io->error('Source database not found: ' . $from);
            return Command::FAILURE;
        }

        try {
            $source = new PDO('sqlite:' . $from);
            $source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $source->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $source->exec('PRAGMA query_only=ON');
            $columns = $source->query('PRAGMA table_info("sales_records")')->fetchAll();
            $hasName = false;
            foreach ($columns as $column) {
                if (isset($column['name']) && $column['name'] === 'name') {
                    $hasName = true;
                }
            }
            if (!$hasName) {
                $io->error('The source file has no sales_records.name column (is this an old Mikhmon DB?).');
                return Command::FAILURE;
            }
        } catch (\Throwable $e) {
            $io->error('Cannot open source database: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $db = mikhmon_database();
        $migrator = new \Mikhmon\Storage\Migrator($db);
        if (!$migrator->isCurrent()) {
            $io->error('Target schema is not migrated. Run: bin/mikhmon db:migrate --backup');
            return Command::FAILURE;
        }

        $sales = new SalesRepository($db);
        $batchSize = max(50, min(5000, (int)$input->getOption('batch')));
        $limit = $input->getOption('limit') !== null ? max(1, (int)$input->getOption('limit')) : null;
        $dry = (bool)$input->getOption('dry-run');
        $sessionOverride = $input->getOption('session') !== null ? (string)$input->getOption('session') : '';

        $stmt = $source->prepare(
            'SELECT id, session, name, ros_script_id, comment
             FROM sales_records WHERE id > ? ORDER BY id LIMIT ' . (int)$batchSize
        );

        $lastId = 0;
        $read = 0;
        $inserted = 0;
        $existing = 0;
        $invalid = 0;
        $invalidSamples = array();

        while (true) {
            $stmt->execute(array($lastId));
            $rows = $stmt->fetchAll();
            if (empty($rows)) {
                break;
            }
            $importedThisBatch = array();
            $sourceIds = array();
            foreach ($rows as $row) {
                $lastId = (int)$row['id'];
                $read++;
                $name = isset($row['name']) ? trim((string)$row['name']) : '';
                $parsed = RecordParser::parse($name);
                if ($parsed === null) {
                    $invalid++;
                    if (count($invalidSamples) < 10) {
                        $invalidSamples[] = $name;
                    }
                } else {
                    $session = $sessionOverride !== ''
                        ? $sessionOverride
                        : (isset($row['session']) && $row['session'] !== '' ? (string)$row['session'] : 'livebarokah');
                    if (!$dry) {
                        $importedThisBatch[] = array($session, $name, $parsed, isset($row['ros_script_id']) ? (string)$row['ros_script_id'] : '', isset($row['comment']) ? (string)$row['comment'] : 'mikhmon');
                    } else {
                        $inserted++;
                    }
                }
                if ($limit !== null && $read >= $limit) {
                    break;
                }
            }

            if (!$dry && !empty($importedThisBatch)) {
                $db->transaction(function () use ($sales, $importedThisBatch, &$inserted, &$existing) {
                    foreach ($importedThisBatch as $item) {
                        if ($sales->insertParsed($item[0], $item[1], $item[2], $item[3], $item[4])) {
                            $inserted++;
                        } else {
                            $existing++;
                        }
                    }
                });
            }

            if (!$input->getOption('json')) {
                $io->writeln(sprintf('  read=%d inserted=%d existing=%d invalid=%d', $read, $inserted, $existing, $invalid));
            }
            if ($limit !== null && $read >= $limit) {
                break;
            }
        }

        $result = array(
            'source' => $from,
            'dry_run' => $dry,
            'read' => $read,
            'inserted' => $inserted,
            'existing' => $existing,
            'invalid' => $invalid,
            'invalid_samples' => $invalidSamples,
            'target_db' => $db->path(),
        );

        if ($input->getOption('json')) {
            $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $io->success(sprintf(
                'Legacy import: read=%d inserted=%d existing=%d invalid=%d',
                $read,
                $inserted,
                $existing,
                $invalid
            ));
            if ($invalidSamples) {
                $io->warning('Sample invalid names: ' . implode(' | ', $invalidSamples));
            }
        }
        return Command::SUCCESS;
    }
}
