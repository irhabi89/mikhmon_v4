<?php
/**
 * On-login webhook configuration.
 *
 * The public URL belongs to the application and comes from MIKHMON_PUBLIC_URL
 * (.env). The token is per router session and is stored in mikhmon_sessions so
 * one router cannot post sales for another session.
 */

if (!function_exists('mikhmon_webhook_public_url')) {
    function mikhmon_webhook_public_url(): string
    {
        $value = class_exists('\\Mikhmon\\Support\\Config')
            ? (string)\Mikhmon\Support\Config::get('MIKHMON_PUBLIC_URL', '')
            : (string)getenv('MIKHMON_PUBLIC_URL');
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        if (!in_array(strtolower((string)$parts['scheme']), array('http', 'https'), true)) {
            return '';
        }
        return rtrim($value, '/');
    }
}

if (!function_exists('mikhmon_webhook_url')) {
    function mikhmon_webhook_url(string $session, string $legacyUrl = ''): string
    {
        $base = mikhmon_webhook_public_url();
        if ($base === '' && trim($legacyUrl) !== '') {
            // Backward compatibility for a URL saved by older versions.
            $legacyUrl = trim($legacyUrl);
            if (strpos($legacyUrl, 'session=') === false) {
                $legacyUrl .= (strpos($legacyUrl, '?') === false ? '?' : '&') . 'session=' . rawurlencode($session);
            }
            return $legacyUrl;
        }
        if ($base === '') {
            return '';
        }
        return $base . '/process/sales_webhook.php?session=' . rawurlencode($session);
    }
}

if (!function_exists('mikhmon_webhook_token')) {
    function mikhmon_webhook_token(string $session, bool $create = false): string
    {
        $row = function_exists('mikhmon_session_get') ? mikhmon_session_get($session) : null;
        $token = is_array($row) && isset($row['webhook_token']) ? trim((string)$row['webhook_token']) : '';
        if ($token === '' && $create && function_exists('mikhmon_session_upsert')) {
            try {
                $token = bin2hex(random_bytes(24));
            } catch (Throwable $e) {
                return '';
            }
            mikhmon_session_upsert(array('name' => $session, 'webhook_token' => $token));
        }
        return $token;
    }
}

if (!function_exists('mikhmon_webhook_ready')) {
    function mikhmon_webhook_ready(string $session, string $legacyUrl = ''): bool
    {
        return mikhmon_webhook_url($session, $legacyUrl) !== '' && mikhmon_webhook_token($session) !== '';
    }
}
