<?php

declare(strict_types=1);

namespace Mikhmon\Support;

/**
 * Convert MikroTik/Mikhmon price strings ("5000", "5.000", "5,000",
 * "Rp 5.000", "5,5") to a numeric value.
 */
final class PriceParser
{
    public static function toFloat($raw): float
    {
        if (is_int($raw) || is_float($raw)) {
            return (float)$raw;
        }
        $value = trim((string)$raw);
        if ($value === '') {
            return 0.0;
        }

        $negative = (strpos($value, '-') !== false);
        $value = preg_replace('/[^0-9,\.]/', '', $value);
        if ($value === null || $value === '') {
            return 0.0;
        }

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                // 1.234,56 -> 1234.56
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                // 1,234.56 -> 1234.56
                $value = str_replace(',', '', $value);
            }
        } elseif ($lastComma !== false) {
            $after = strlen($value) - $lastComma - 1;
            if ($after >= 1 && $after <= 2) {
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif ($lastDot !== false) {
            $dots = substr_count($value, '.');
            $after = strlen($value) - $lastDot - 1;
            if ($dots > 1) {
                $value = str_replace('.', '', $value);
            } elseif ($after === 3) {
                // 5.000 is far more likely to be an IDR thousands separator.
                $value = str_replace('.', '', $value);
            } elseif ($after === 0) {
                $value = rtrim($value, '.');
            }
        }

        $number = (float)$value;
        return $negative ? -$number : $number;
    }

    public static function format(float $value, string $currency = 'Rp', int $decimals = 0): string
    {
        return $currency . ' ' . number_format($value, $decimals, ',', '.');
    }
}
