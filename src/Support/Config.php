<?php

declare(strict_types=1);

namespace Mikhmon\Support;

/**
 * Environment-backed configuration with safe defaults.
 * PHP 7.4 compatible.
 */
final class Config
{
    /** @var string */
    private static $baseDir = '';

    /** @var bool */
    private static $loaded = false;

    /** @var array<string,mixed> */
    private static $defaults = array(
        'MIKHMON_DB_PATH' => 'data/mikhmon.db',
        'MIKHMON_PUBLIC_URL' => '',
        'MIKHMON_ROUTER_TRANSPORT' => 'native',
        'MIKHMON_SYNC_WORKER' => 'auto',
        'MIKHMON_SYNC_BATCH' => '500',
        'MIKHMON_SYNC_RANGE_ROWS' => '2000',
        'MIKHMON_SYNC_LOOKBACK_MONTHS' => '36',
        'MIKHMON_LOG_LEVEL' => 'info',
        'MIKHMON_AUTO_MIGRATE' => '0',
        'MIKHMON_TIMEZONE' => 'Asia/Jakarta',
        'MIKHMON_PHP_CLI' => '',
    );

    public static function load(string $baseDir): void
    {
        if (self::$loaded) {
            return;
        }
        self::$baseDir = rtrim($baseDir, '/');
        if (class_exists('Dotenv\\Dotenv') && is_file(self::$baseDir . '/.env')) {
            try {
                $dotenv = \Dotenv\Dotenv::createImmutable(self::$baseDir);
                $dotenv->safeLoad();
            } catch (\Throwable $e) {
                // Configuration must not take the whole site down.
                error_log('mikhmon dotenv: ' . $e->getMessage());
            }
        }
        self::$loaded = true;
    }

    public static function baseDir(): string
    {
        if (self::$baseDir === '') {
            self::load(dirname(__DIR__, 2));
        }
        return self::$baseDir;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            if (array_key_exists($key, self::$defaults)) {
                return self::$defaults[$key];
            }
            return $default;
        }
        return $value;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);
        return is_numeric($value) ? (int)$value : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default ? '1' : '0');
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string)$value), array('1', 'true', 'yes', 'on'), true);
    }

    public static function dbPath(): string
    {
        $path = (string)self::get('MIKHMON_DB_PATH', 'data/mikhmon.db');
        if (preg_match('#^(/|[A-Za-z]:[\\\\/])#', $path) !== 1) {
            $path = self::baseDir() . '/' . ltrim($path, '/');
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $path;
    }

    public static function logDir(): string
    {
        $dir = self::baseDir() . '/data/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    public static function autoMigrate(): bool
    {
        return self::bool('MIKHMON_AUTO_MIGRATE', false);
    }

    public static function timezone(): string
    {
        return (string)self::get('MIKHMON_TIMEZONE', 'Asia/Jakarta');
    }
}
