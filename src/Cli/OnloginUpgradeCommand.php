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

final class OnloginUpgradeCommand extends Command
{
    protected static $defaultName = 'router:onlogin-upgrade';

    protected function configure(): void
    {
        $this->setDescription('Add server-aware recording to Mikhmon-generated hotspot on-login scripts')
            ->addOption('session', null, InputOption::VALUE_REQUIRED, 'Router session name')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Apply changes (without this it is a dry run)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $session = (string)$input->getOption('session');
        if ($session === '') {
            $io->error('--session is required');
            return Command::FAILURE;
        }
        $db = mikhmon_database();
        $upgrader = new OnloginUpgrader(new RouterClientFactory($db));
        $result = $upgrader->upgrade($session, (bool)$input->getOption('apply'));

        if ($input->getOption('json')) {
            $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $io->table(array('Key', 'Value'), array(
                array('profiles', (string)$result['profiles']),
                array('upgraded', (string)$result['upgraded']),
                array('skipped', (string)$result['skipped']),
                array('backup', $result['backup'] !== '' ? $result['backup'] : '-'),
                array('mode', $input->getOption('apply') ? 'APPLY' : 'DRY RUN'),
            ));
            foreach ($result['errors'] as $error) {
                $io->error($error);
            }
        }
        return $result['ok'] ? Command::SUCCESS : Command::FAILURE;
    }
}
