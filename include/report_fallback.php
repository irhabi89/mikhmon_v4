<?php

declare(strict_types=1);

/**
 * Resilient session lookup and router client construction with legacy
 * include/config.php fallback when SQLite is unavailable or corrupted.
 */

if (!function_exists('mikhmon_safe_session_row')) {
    /**
     * @return array<string,mixed>|null
     */
    function mikhmon_safe_session_row(string $session): ?array
    {
        if ($session === '' || preg_match('/^[A-Za-z0-9_.-]+$/', $session) !== 1) {
            return null;
        }

        try {
            if (function_exists('mikhmon_database')) {
                $db = mikhmon_database();
                $row = $db->fetchOne('SELECT * FROM mikhmon_sessions WHERE name = ?', array($session));
                if (is_array($row)) {
                    return $row;
                }
            }
        } catch (Throwable $e) {
            // Fall through to legacy include/config.php.
        }

        $cfgFile = dirname(__DIR__) . '/include/config.php';
        $storeFile = dirname(__DIR__) . '/lib/sessions_store.php';
        if (is_file($cfgFile) && is_file($storeFile)) {
            $data = array();
            include $cfgFile;
            require_once $storeFile;
            if (isset($data[$session]) && is_array($data[$session]) && function_exists('mikhmon_row_from_data')) {
                $row = mikhmon_row_from_data($session, $data[$session]);
                return is_array($row) ? $row : null;
            }
        }
        return null;
    }
}

if (!function_exists('mikhmon_safe_router_client')) {
    function mikhmon_safe_router_client(string $session, bool $fast = false): \Mikhmon\Router\RouterClientInterface
    {
        $row = mikhmon_safe_session_row($session);
        if (!is_array($row)) {
            throw new \Mikhmon\Router\RouterException('router session not found: ' . $session);
        }

        if (!function_exists('decrypt')) {
            require_once dirname(__DIR__) . '/lib/routeros_api.class.php';
        }

        $target = \Mikhmon\Router\RouterTarget::fromString(
            isset($row['ip_host']) ? (string)$row['ip_host'] : '',
            isset($row['port_host']) ? (int)$row['port_host'] : 8728
        );
        $password = \decrypt(isset($row['pass_host']) ? (string)$row['pass_host'] : '');
        $options = $fast
            ? array(
                'attempts' => 1,
                'delay' => 0,
                'timeout' => 3,
                'read_timeout' => 30,
                'debug' => false,
            )
            : array(
                'attempts' => 2,
                'delay' => 1,
                'timeout' => 5,
                'read_timeout' => 300,
                'debug' => false,
            );
        return new \Mikhmon\Router\NativeRouterClient(
            $target,
            isset($row['user_host']) ? (string)$row['user_host'] : '',
            $password,
            $options
        );
    }
}

if (!function_exists('mikhmon_write_live_compare_cache')) {
    /**
     * @param array<string,mixed> $compare
     */
    function mikhmon_write_live_compare_cache(string $session, array $compare): void
    {
        $cacheDir = dirname(__DIR__) . '/data/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        @file_put_contents(
            $cacheDir . '/live-compare-' . hash('sha256', $session) . '.json',
            json_encode($compare, JSON_UNESCAPED_SLASHES)
        );
    }
}

if (!function_exists('mikhmon_clear_live_compare_cache')) {
    function mikhmon_clear_live_compare_cache(string $session): void
    {
        $file = dirname(__DIR__) . '/data/cache/live-compare-' . hash('sha256', $session) . '.json';
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

if (!function_exists('mikhmon_live_cache_read')) {
    /**
     * @return array<string,mixed>|null
     */
    function mikhmon_live_cache_read(string $bucket, string $key, int $ttlSeconds = 15): ?array
    {
        if ($ttlSeconds <= 0) {
            return null;
        }
        $safeBucket = preg_replace('/[^A-Za-z0-9_-]+/', '-', $bucket);
        $file = dirname(__DIR__) . '/data/cache/' . $safeBucket . '-' . hash('sha256', $key) . '.json';
        if (!is_file($file)) {
            return null;
        }
        $mtime = @filemtime($file);
        if ($mtime === false || (time() - $mtime) > $ttlSeconds) {
            return null;
        }
        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}

if (!function_exists('mikhmon_live_cache_write')) {
    /**
     * @param array<string,mixed> $payload
     */
    function mikhmon_live_cache_write(string $bucket, string $key, array $payload): void
    {
        $cacheDir = dirname(__DIR__) . '/data/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $safeBucket = preg_replace('/[^A-Za-z0-9_-]+/', '-', $bucket);
        $file = $cacheDir . '/' . $safeBucket . '-' . hash('sha256', $key) . '.json';
        $tmp = $file . '.tmp.' . getmypid();
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return;
        }
        if (@file_put_contents($tmp, $json) !== false) {
            @rename($tmp, $file);
        } else {
            @unlink($tmp);
        }
    }
}
