<?php

declare(strict_types=1);

namespace Mikhmon\Support;

/**
 * Thin Monolog wrapper with a graceful fallback when vendor is absent.
 */
final class Log
{
    /** @var \Monolog\Logger|null */
    private static $logger = null;

    /** @var bool */
    private static $initialised = false;

    public static function init(): void
    {
        if (self::$initialised) {
            return;
        }
        self::$initialised = true;
        if (!class_exists('Monolog\\Logger') || !class_exists('Monolog\\Handler\\StreamHandler')) {
            return;
        }
        try {
            $levelName = strtoupper((string)Config::get('MIKHMON_LOG_LEVEL', 'info'));
            $levels = array(
                'DEBUG' => \Monolog\Logger::DEBUG,
                'INFO' => \Monolog\Logger::INFO,
                'NOTICE' => \Monolog\Logger::NOTICE,
                'WARNING' => \Monolog\Logger::WARNING,
                'ERROR' => \Monolog\Logger::ERROR,
                'CRITICAL' => \Monolog\Logger::CRITICAL,
            );
            $level = isset($levels[$levelName]) ? $levels[$levelName] : \Monolog\Logger::INFO;
            $handler = new \Monolog\Handler\StreamHandler(Config::logDir() . '/app.log', $level);
            $handler->setFormatter(new \Monolog\Formatter\LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'Y-m-d H:i:s',
                true,
                true
            ));
            self::$logger = new \Monolog\Logger('mikhmon');
            self::$logger->pushHandler($handler);
        } catch (\Throwable $e) {
            error_log('mikhmon logger: ' . $e->getMessage());
            self::$logger = null;
        }
    }

    public static function logger()
    {
        self::init();
        return self::$logger;
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function info(string $message, array $context = array()): void
    {
        self::write('info', $message, $context);
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function warning(string $message, array $context = array()): void
    {
        self::write('warning', $message, $context);
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function error(string $message, array $context = array()): void
    {
        self::write('error', $message, $context);
    }

    /**
     * @param string              $level
     * @param string              $message
     * @param array<string,mixed> $context
     */
    private static function write(string $level, string $message, array $context = array()): void
    {
        self::init();
        if (self::$logger !== null) {
            self::$logger->log($level, $message, $context);
            return;
        }
        error_log('mikhmon ' . $level . ': ' . $message . ($context ? ' ' . json_encode($context) : ''));
    }
}
