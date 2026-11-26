<?php

declare(strict_types=1);

/*
 * Composer/bootstrap for the new sales core.
 * Legacy pages may still include their own files; this bootstrap is used by
 * the report pages, sync endpoint and CLI.
 */

$mikhmon_base = dirname(__DIR__);
$mikhmon_autoload = $mikhmon_base . '/vendor/autoload.php';
if (!is_file($mikhmon_autoload)) {
    throw new RuntimeException('Dependencies are not installed. Run: composer install');
}
require_once $mikhmon_autoload;

if (PHP_SAPI !== 'cli') {
    @ini_set('display_errors', '0');
    @ini_set('log_errors', '1');
}
\Mikhmon\Support\Config::load($mikhmon_base);
require_once $mikhmon_base . '/lib/webhook.php';
\Mikhmon\Support\Log::init();
@date_default_timezone_set(\Mikhmon\Support\Config::timezone());

if (!function_exists('mikhmon_database')) {
    function mikhmon_database(): \Mikhmon\Storage\Database
    {
        static $db = null;
        if ($db === null) {
            $db = new \Mikhmon\Storage\Database(\Mikhmon\Support\Config::dbPath());
        }
        return $db;
    }
}

if (!function_exists('mikhmon_require_schema')) {
    /**
     * Fail with a clear message instead of showing an empty report when the
     * baseline migration has not been applied yet.
     */
    function mikhmon_require_schema(): void
    {
        $db = mikhmon_database();
        $migrator = new \Mikhmon\Storage\Migrator($db);
        if (!$migrator->isCurrent()) {
            throw new RuntimeException('Database schema is not migrated. Run: bin/mikhmon db:migrate --backup');
        }
    }
}

if (isset($_GET['session']) && class_exists('\\Mikhmon\\Support\\ResultCache')) {
    $mikhmon_mutation_keys = array(
        'remove-hotspot-user', 'remove-hotspot-users', 'remove-hotspot-user-by-comment',
        'remove-hotspot-user-expired', 'remove-host', 'remove-cookie', 'remove-ip-binding',
        'remove-user-active', 'remove-user-profile', 'reset-hotspot-user',
        'enable-hotspot-user', 'disable-hotspot-user', 'remove-pactive', 'remove-pprofile',
        'remove-pppsecret', 'enable-pppsecret', 'disable-pppsecret',
        'remove-scheduler', 'enable-scheduler', 'disable-scheduler',
    );
    $mikhmon_is_mutation = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST');
    if (!$mikhmon_is_mutation) {
        foreach ($mikhmon_mutation_keys as $mikhmon_mutation_key) {
            if (isset($_GET[$mikhmon_mutation_key])) { $mikhmon_is_mutation = true; break; }
        }
    }
    if ($mikhmon_is_mutation) {
        \Mikhmon\Support\ResultCache::flushSession((string)$_GET['session']);
    }
}
