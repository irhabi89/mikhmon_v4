<?php

declare(strict_types=1);

namespace Mikhmon\Cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Clear local sales data for a clean import test. Router scripts are NOT touched.
 */
final class DbClearSalesCommand extends Command
{
    protected static $defaultName = 'db:clear-sales';

    protected function configure(): void
    {
        $this->setDescription('Delete local sales_records/snapshot/run rows (router scripts are untouched)')
            ->addOption('session', null, InputOption::VALUE_REQUIRED, 'Router session name')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Clear all sessions')
            ->addOption('yes', null, InputOption::VALUE_NONE, 'Really delete (required)')
            ->addOption('no-backup', null, InputOption::VALUE_NONE, 'Skip the automatic database backup');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$input->getOption('yes')) {
            $io->error('Refusing to delete without --yes. This only clears local DB data; router scripts are untouched.');
            return Command::FAILURE;
        }
        $session = (string)$input->getOption('session');
        $all = (bool)$input->getOption('all');
        if (!$all && $session === '') {
            $io->error('Use --session=NAME or --all');
            return Command::FAILURE;
        }

        $db = mikhmon_database();
        if (!$input->getOption('no-backup')) {
            $io->writeln('Backup: ' . DatabaseTools::backup($db));
        }

        if ($all) {
            $sales = $db->execute('DELETE FROM sales_records');
            $snapshot = $db->execute('DELETE FROM sales_router_records');
            $runs = $db->execute('DELETE FROM sales_sync_runs');
        } else {
            $sales = $db->execute('DELETE FROM sales_records WHERE session = ?', array($session));
            $snapshot = $db->execute('DELETE FROM sales_router_records WHERE session = ?', array($session));
            $runs = $db->execute('DELETE FROM sales_sync_runs WHERE session = ?', array($session));
        }

        $io->success(sprintf('Cleared local DB: sales=%d snapshot=%d runs=%d. Router scripts were not touched.', $sales, $snapshot, $runs));
        return Command::SUCCESS;
    }
}
