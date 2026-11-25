<?php

declare(strict_types=1);

namespace Mikhmon\Cli;

use Symfony\Component\Console\Application as BaseApplication;

final class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('mikhmon', '1.0');
        $this->add(new DbMigrateCommand());
        $this->add(new DbMaintenanceCommand());
        $this->add(new DbImportLegacyCommand());
        $this->add(new DbClearSalesCommand());
        $this->add(new SyncRunCommand());
        $this->add(new SyncWorkerCommand());
        $this->add(new SyncStatusCommand());
    }
}
