<?php

declare(strict_types=1);

namespace Mikhmon\Cli;

use Mikhmon\Storage\Migrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class DbMigrateCommand extends Command
{
    protected static $defaultName = 'db:migrate';

    protected function configure(): void
    {
        $this->setDescription('Create/upgrade the sales schema (old sales tables are archived)')
            ->addOption('backup', null, InputOption::VALUE_NONE, 'Create a backup before migrating');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $db = mikhmon_database();

        if ($input->getOption('backup')) {
            try {
                $path = DatabaseTools::backup($db);
                $io->writeln('Backup: ' . $path);
            } catch (\Throwable $e) {
                $io->error('Backup failed: ' . $e->getMessage());
                return Command::FAILURE;
            }
        }

        $migrator = new Migrator($db);
        if ($migrator->isCurrent()) {
            $io->success('Schema is already up to date (version ' . $migrator->currentVersion() . ').');
            return Command::SUCCESS;
        }

        try {
            $notes = $migrator->migrate();
            foreach ($notes as $note) {
                $io->writeln(' - ' . $note);
            }
            $io->success('Database migrated to version ' . Migrator::VERSION . '.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Migration failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
