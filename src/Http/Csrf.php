<?php

declare(strict_types=1);

namespace Mikhmon\Http;

final class Csrf
{
    public static function token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (empty($_SESSION['mikhmon_csrf']) || !is_string($_SESSION['mikhmon_csrf'])) {
            $_SESSION['mikhmon_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['mikhmon_csrf'];
    }

    public static function check(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $expected = isset($_SESSION['mikhmon_csrf']) ? (string)$_SESSION['mikhmon_csrf'] : '';
        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }
}
