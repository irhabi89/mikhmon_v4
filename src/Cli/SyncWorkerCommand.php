<?php

declare(strict_types=1);

namespace Mikhmon\Cli;

use Mikhmon\Sync\SalesSyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class SyncWorkerCommand extends Command
{
    protected static $defaultName = 'sync:worker';

    protected function configure(): void
    {
        $this->setDescription('Execute a queued sync run (used by the background worker)')
            ->addOption('run-id', null, InputOption::VALUE_REQUIRED, 'sales_sync_runs.id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $runId = (int)$input->getOption('run-id');
        if ($runId <= 0) {
            $io->error('--run-id is required');
            return Command::FAILURE;
        }
        try {
            $service = new SalesSyncService(mikhmon_database());
            $status = $service->runWorker($runId);
            $output->writeln(json_encode(array(
                'run_id' => $runId,
                'run' => $status['run'],
                'db' => $status['db'],
                'router' => $status['router'],
                'in_sync' => $status['in_sync'],
            ), JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
