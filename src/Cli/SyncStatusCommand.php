<?php

declare(strict_types=1);

namespace Mikhmon\Cli;

use Mikhmon\Sync\SalesSyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class SyncStatusCommand extends Command
{
    protected static $defaultName = 'sync:status';

    protected function configure(): void
    {
        $this->setDescription('Print sync/reconciliation status from SQLite (no router connection)')
            ->addOption('session', null, InputOption::VALUE_REQUIRED, 'Router session name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $session = (string)$input->getOption('session');
        if ($session === '') {
            $output->writeln('<error>--session is required</error>');
            return Command::FAILURE;
        }
        $service = new SalesSyncService(mikhmon_database());
        $output->writeln(json_encode($service->status($session), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return Command::SUCCESS;
    }
}
