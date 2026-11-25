<?php

declare(strict_types=1);

namespace Mikhmon\Cli;

use Mikhmon\Sync\SalesSyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class SyncRunCommand extends Command
{
    protected static $defaultName = 'sync:run';

    protected function configure(): void
    {
        $this->setDescription('Scan the router, import all sales records and verify the result')
            ->addOption('session', null, InputOption::VALUE_REQUIRED, 'Router session name')
            ->addOption('month', null, InputOption::VALUE_REQUIRED, 'Only scan one month (MMMYYYY or YYYY-MM)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the status as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $session = (string)$input->getOption('session');
        if ($session === '') {
            $io->error('--session is required');
            return Command::FAILURE;
        }
        try {
            $service = new SalesSyncService(mikhmon_database());
            $status = $service->runNow($session, $input->getOption('month') ? (string)$input->getOption('month') : null);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        if ($input->getOption('json')) {
            $output->writeln(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $io->table(array('Key', 'Value'), array(
            array('session', $status['session']),
            array('source', $status['source']),
            array('router records', (string)$status['router']['records']),
            array('db records', (string)$status['db']['total']),
            array('matched', (string)$status['db']['matched']),
            array('missing', (string)$status['db']['missing']),
            array('extra', $status['db']['extra'] === null ? 'n/a' : (string)$status['db']['extra']),
            array('invalid', (string)$status['db']['invalid']),
            array('coverage', $status['coverage'] === null ? 'n/a' : $status['coverage'] . '%'),
            array('exact', $status['exact'] === null ? 'n/a' : $status['exact'] . '%'),
            array('in sync', $status['in_sync'] ? 'yes' : 'no'),
        ));
        return Command::SUCCESS;
    }
}
