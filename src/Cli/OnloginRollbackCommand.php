<?php

declare(strict_types=1);

namespace Mikhmon\Cli;

use Mikhmon\Router\OnloginUpgrader;
use Mikhmon\Router\RouterClientFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class OnloginRollbackCommand extends Command
{
    protected static $defaultName = 'router:onlogin-rollback';

    protected function configure(): void
    {
        $this->setDescription('Restore hotspot on-login scripts from an upgrade backup')
            ->addOption('session', null, InputOption::VALUE_REQUIRED, 'Router session name')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Backup JSON produced by router:onlogin-upgrade');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $session = (string)$input->getOption('session');
        $file = (string)$input->getOption('file');
        if ($session === '' || $file === '') {
            $io->error('--session and --file are required');
            return Command::FAILURE;
        }
        $result = (new OnloginUpgrader(new RouterClientFactory(mikhmon_database())))->rollback($session, $file);
        foreach ($result['errors'] as $error) {
            $io->error($error);
        }
        $io->success('Restored: ' . $result['restored']);
        return $result['ok'] ? Command::SUCCESS : Command::FAILURE;
    }
}
