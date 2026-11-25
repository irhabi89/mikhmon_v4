<?php

declare(strict_types=1);

namespace Mikhmon\Cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class DbMaintenanceCommand extends Command
{
    protected static $defaultName = 'db:maintenance';

    protected function configure(): void
    {
        $this->setDescription('Check, backup, vacuum and optimize the SQLite database')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Print integrity and size information')
            ->addOption('backup', null, InputOption::VALUE_NONE, 'Create a backup')
            ->addOption('vacuum', null, InputOption::VALUE_NONE, 'Reclaim free pages (needs free disk space)')
            ->addOption('optimize', null, InputOption::VALUE_NONE, 'Run PRAGMA optimize')
            ->addOption('reset-queue', null, InputOption::VALUE_NONE, 'Clear the old (pre-v2) import queue');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $db = mikhmon_database();

        $any = false;
        if ($input->getOption('backup')) {
            $any = true;
            $io->writeln('Backup: ' . DatabaseTools::backup($db));
        }
        if ($input->getOption('reset-queue')) {
            $any = true;
            DatabaseTools::resetLegacyQueue($db);
            $io->writeln('Old import queue cleared.');
        }
        if ($input->getOption('optimize')) {
            $any = true;
            $db->optimize();
            $io->writeln('PRAGMA optimize done.');
        }
        if ($input->getOption('vacuum')) {
            $any = true;
            $io->writeln('Running VACUUM (this can take a while)...');
            $db->checkpoint();
            $db->vacuum();
            $io->writeln('VACUUM done.');
        }

        if ($input->getOption('check') || !$any) {
            $rows = array();
            foreach (DatabaseTools::check($db) as $key => $value) {
                $rows[] = array($key, is_scalar($value) ? (string)$value : json_encode($value));
            }
            $io->table(array('Key', 'Value'), $rows);
        }

        return Command::SUCCESS;
    }
}
