<?php

declare(strict_types=1);

namespace Mikhmon\Support;

/**
 * Small file-backed cache for complete, read-only RouterOS results.
 * It deliberately has no database migration: reports keep using SQLite,
 * while short-lived UI list/count results live in data/cache.
 */
final class ResultCache
{
    public const VERSION = 1;

    /** @var array<string,mixed> */
    private static $memo = array();

    public static function ttl(int $default = 15): int
    {
        $configured = Config::int('MIKHMON_CACHE_TTL', $default);
        return max(0, min(300, $configured));
    }

    public static function bypass(): bool
    {
        return (isset($_GET['refresh']) && (string)$_GET['refresh'] === '1')
            || (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'POST');
    }

    /**
     * @param callable():mixed $producer
     * @return array{data:mixed,cached:bool,created_at:int,stale:bool,error:string}
     */
    public static function remember(string $session, string $key, int $ttl, callable $producer): array
    {
        $ttl = max(0, min(300, $ttl));
        $hash = self::hash($session, $key);
        $memoKey = $hash;
        if ($ttl > 0 && !self::bypass() && isset(self::$memo[$memoKey])) {
            $hit = self::$memo[$memoKey];
            $hit['cached'] = true;
            return $hit;
        }

        $cached = ($ttl > 0 && !self::bypass()) ? self::readFile($hash, $session) : null;
        if ($cached !== null && $cached['expires_at'] >= time()) {
            $result = array(
                'data' => $cached['data'],
                'cached' => true,
                'created_at' => (int)$cached['created_at'],
                'stale' => false,
                'error' => '',
            );
            self::$memo[$memoKey] = $result;
            return $result;
        }

        try {
            $data = $producer();
            $result = array(
                'data' => $data,
                'cached' => false,
                'created_at' => time(),
                'stale' => false,
                'error' => '',
            );
            if ($ttl > 0) {
                self::writeFile($hash, $session, $data, $ttl);
            }
            self::$memo[$memoKey] = $result;
            return $result;
        } catch (\Throwable $e) {
            if ($cached !== null) {
                $result = array(
                    'data' => $cached['data'],
                    'cached' => true,
                    'created_at' => (int)$cached['created_at'],
                    'stale' => true,
                    'error' => $e->getMessage(),
                );
                self::$memo[$memoKey] = $result;
                return $result;
            }
            throw $e;
        }
    }

    /**
     * @param mixed $data
     */
    public static function put(string $session, string $key, $data, int $ttl): void
    {
        self::writeFile(self::hash($session, $key), $session, $data, max(1, min(300, $ttl)));
    }

    /**
     * @return mixed|null
     */
    public static function get(string $session, string $key)
    {
        $hash = self::hash($session, $key);
        if (isset(self::$memo[$hash])) {
            return self::$memo[$hash]['data'];
        }
        $cached = self::readFile($hash, $session);
        if ($cached === null || $cached['expires_at'] < time()) {
            return null;
        }
        return $cached['data'];
    }

    public static function flushSession(string $session): void
    {
        self::$memo = array();
        $prefix = substr(sha1('session:' . $session), 0, 16) . '-';
        foreach ((array)glob(self::dir() . '/' . $prefix . '*.json') as $file) {
            @unlink($file);
        }
    }

    public static function flushAll(): void
    {
        self::$memo = array();
        foreach ((array)glob(self::dir() . '/*.json') as $file) {
            @unlink($file);
        }
    }

    public static function dir(): string
    {
        $dir = Config::baseDir() . '/data/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $guard = $dir . '/.htaccess';
        if (!is_file($guard)) {
            @file_put_contents($guard, "Require all denied\nDeny from all\n");
        }
        return $dir;
    }

    private static function hash(string $session, string $key): string
    {
        $prefix = substr(sha1('session:' . $session), 0, 16);
        return $prefix . '-' . substr(hash('sha256', self::VERSION . '|' . $session . '|' . $key), 0, 32);
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function readFile(string $hash, string $session): ?array
    {
        $file = self::dir() . '/' . $hash . '.json';
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['session']) || $data['session'] !== $session) {
            @unlink($file);
            return null;
        }
        return $data;
    }

    /**
     * @param mixed $data
     */
    private static function writeFile(string $hash, string $session, $data, int $ttl): void
    {
        $payload = array(
            'version' => self::VERSION,
            'session' => $session,
            'created_at' => time(),
            'expires_at' => time() + max(0, $ttl),
            'data' => $data,
        );
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return;
        }
        $file = self::dir() . '/' . $hash . '.json';
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $json, LOCK_EX) !== false) {
            @rename($tmp, $file);
        } else {
            @unlink($tmp);
        }
    }
}
