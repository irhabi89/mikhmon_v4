<?php

declare(strict_types=1);

namespace Mikhmon\Http;

/**
 * Minimal PHP view renderer. Views receive only the data passed to render();
 * they must not perform SQL or router calls.
 */
final class View
{
    /**
     * @param array<string,mixed> $data
     */
    public static function render(string $template, array $data = array()): void
    {
        echo self::capture($template, $data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function capture(string $template, array $data = array()): string
    {
        $file = dirname(__DIR__, 2) . '/resources/views/' . ltrim($template, '/') . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $template);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string)ob_get_clean();
    }
}
