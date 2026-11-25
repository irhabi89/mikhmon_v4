<?php

declare(strict_types=1);

namespace Mikhmon\Http;

final class Json
{
    /**
     * @param mixed $data
     */
    public static function send($data, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
    }
}
