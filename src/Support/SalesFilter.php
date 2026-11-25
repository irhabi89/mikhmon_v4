<?php

declare(strict_types=1);

namespace Mikhmon\Support;

/**
 * Normalises the report filter query string once for HTML, CSV and print.
 */
final class SalesFilter
{
    /** @var array<int,string> */
    private const MONTHS = array(
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    );

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function fromQuery(array $query): array
    {
        $dateKey = self::validDate($query['date_key'] ?? null);
        if ($dateKey === null && !empty($query['idhr'])) {
            $dateKey = RecordParser::dateKeyFromLegacy((string)$query['idhr']);
        }
        $dateFrom = self::validDate($query['date_from'] ?? null);
        $dateTo = self::validDate($query['date_to'] ?? null);
        if ($dateFrom === null && $dateKey !== null) {
            $dateFrom = $dateKey;
        }
        if ($dateTo === null && $dateKey !== null) {
            $dateTo = $dateKey;
        }
        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            $swap = $dateFrom;
            $dateFrom = $dateTo;
            $dateTo = $swap;
        }

        $monthKey = null;
        if (!empty($query['month_key']) && preg_match('/^\d{4}-\d{2}$/', (string)$query['month_key'])) {
            $monthKey = (string)$query['month_key'];
        } elseif (!empty($query['idbl'])) {
            $monthKey = RecordParser::monthKeyFromBucket((string)$query['idbl']);
        }

        if ($dateKey !== null) {
            $monthKey = null;
        }

        $monthName = null;
        if (!empty($query['month_name'])) {
            $m = (int)$query['month_name'];
            if ($m >= 1 && $m <= 12) {
                $monthName = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
            }
        }
        $year = null;
        if (!empty($query['year']) && preg_match('/^\d{4}$/', (string)$query['year'])) {
            $year = (string)$query['year'];
        }
        $server = isset($query['server']) ? trim((string)$query['server']) : '';
        $q = isset($query['q']) ? trim((string)$query['q']) : '';
        if ($q === '' && isset($query['prefix'])) {
            $q = trim((string)$query['prefix']);
        }

        return array(
            'filter' => array(
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'month_key' => $monthKey,
                'month_name' => $monthName,
                'year' => $year,
                'q' => $q,
                'server' => $server,
            ),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'date_key' => $dateKey,
            'month_key' => $monthKey,
            'month_name' => $monthName,
            'year' => $year,
            'q' => $q,
            'server' => $server,
        );
    }

    /**
     * Query parameters that reproduce this filter (without pagination).
     * @param array<string,mixed> $meta
     * @return array<string,string>
     */
    public static function query(array $meta, string $session): array
    {
        $out = array('report' => 'selling', 'session' => $session);
        foreach (array('date_from', 'date_to', 'month_name', 'year', 'month_key', 'q', 'server') as $key) {
            if (isset($meta[$key]) && $meta[$key] !== null && $meta[$key] !== '') {
                $out[$key] = (string)$meta[$key];
            }
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $meta
     */
    public static function label(array $meta): string
    {
        $parts = array();
        if (!empty($meta['date_from']) || !empty($meta['date_to'])) {
            $from = !empty($meta['date_from']) ? self::formatDate((string)$meta['date_from']) : 'awal';
            $to = !empty($meta['date_to']) ? self::formatDate((string)$meta['date_to']) : 'sekarang';
            $parts[] = $from === $to ? $from : $from . ' – ' . $to;
        }
        if (!empty($meta['month_name'])) {
            $parts[] = self::monthName((string)$meta['month_name']) . (!empty($meta['year']) ? ' ' . $meta['year'] : '');
        } elseif (!empty($meta['year'])) {
            $parts[] = 'Tahun ' . $meta['year'];
        }
        if (!empty($meta['month_key'])) {
            $parts[] = date('F Y', strtotime($meta['month_key'] . '-01'));
        }
        return $parts ? implode(' · ', $parts) : 'Semua data';
    }

    /** @return array<int,string> */
    public static function months(): array
    {
        return self::MONTHS;
    }

    public static function monthName(string $month): string
    {
        $m = (int)$month;
        return isset(self::MONTHS[$m]) ? self::MONTHS[$m] : $month;
    }

    public static function formatDate(string $date): string
    {
        $ts = strtotime($date);
        return $ts === false ? $date : date('d M Y', $ts);
    }

    private static function validDate($value): ?string
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        return null;
    }
}
