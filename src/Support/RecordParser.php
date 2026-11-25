<?php

declare(strict_types=1);

namespace Mikhmon\Support;

/**
 * Parser for Mikhmon sales scripts.
 *
 * A record name has nine "-|-" separated fields:
 *   date | time | username | price | address | mac | validity | profile | comment
 *
 * Both legacy ("nov/19/2026") and RouterOS 7 ("2026-11-19") dates are accepted.
 */
final class RecordParser
{
    /** @var string[] */
    private static $months = array(
        'jan' => '01', 'feb' => '02', 'mar' => '03', 'apr' => '04',
        'may' => '05', 'jun' => '06', 'jul' => '07', 'aug' => '08',
        'sep' => '09', 'oct' => '10', 'nov' => '11', 'dec' => '12',
    );

    /**
     * @return array<string,mixed>|null
     */
    public static function parse(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $cols = explode('-|-', $name, 9);
        if (count($cols) < 9) {
            return null;
        }

        $sourceDate = trim((string)$cols[0]);
        $dateKey = self::dateKey($sourceDate);
        if ($dateKey === null) {
            return null;
        }

        $timeKey = trim((string)$cols[1]);
        if ($timeKey !== '' && preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $timeKey) !== 1) {
            // Keep the raw value but do not reject a record for a display field.
            $timeKey = trim((string)$cols[1]);
        }

        $priceRaw = trim((string)$cols[3]);

        return array(
            'source_date' => $sourceDate,
            'date_key' => $dateKey,
            'month_key' => substr($dateKey, 0, 7),
            'time_key' => $timeKey,
            'username' => trim((string)$cols[2]),
            'price' => PriceParser::toFloat($priceRaw),
            'price_raw' => $priceRaw,
            'address' => trim((string)$cols[4]),
            'mac' => trim((string)$cols[5]),
            'validity' => trim((string)$cols[6]),
            'profile' => trim((string)$cols[7]),
            'sale_comment' => trim((string)$cols[8]),
        );
    }

    /**
     * A script is a sales record when it carries the mikhmon marker or when
     * its name has the expected nine-field shape with a parseable date.
     */
    public static function isSalesRecord(string $name, string $scriptComment = ''): bool
    {
        if (strtolower(trim($scriptComment)) === 'mikhmon') {
            return true;
        }
        return self::parse($name) !== null;
    }

    public static function dateKey(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $raw, $m)) {
            $year = (int)$m[1];
            $month = (int)$m[2];
            $day = (int)$m[3];
        } elseif (preg_match('/^([A-Za-z]{3})\/(\d{1,2})\/(\d{2,4})$/', $raw, $m)) {
            $monthKey = strtolower($m[1]);
            if (!isset(self::$months[$monthKey])) {
                return null;
            }
            $month = (int)self::$months[$monthKey];
            $day = (int)$m[2];
            $year = (int)$m[3];
            if ($year < 100) {
                $year += 2000;
            }
        } else {
            return null;
        }

        if (!checkdate($month, $day, $year)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Exclusive upper bound for a lexicographic name prefix.
     * RouterOS rejects a 0xFF byte in query values, so increment the last
     * character instead (e.g. "nov/" -> "nov0", "2026-11" -> "2026-12").
     */
    public static function prefixUpperBound(string $prefix): string
    {
        if ($prefix === '') {
            return '';
        }
        $last = substr($prefix, -1);
        $byte = ord($last);
        if ($byte >= 0x7e) {
            return $prefix . "\x7f";
        }
        return substr($prefix, 0, -1) . chr($byte + 1);
    }

    /**
     * Convert "nov2026" to "2026-11".
     */
    public static function monthKeyFromBucket(string $bucket): ?string
    {
        $bucket = strtolower(trim($bucket));
        if (preg_match('/^([a-z]{3})(\d{4})$/', $bucket, $m) !== 1) {
            return null;
        }
        if (!isset(self::$months[$m[1]])) {
            return null;
        }
        return $m[2] . '-' . self::$months[$m[1]];
    }

    /**
     * Convert "2026-11" to the legacy "nov2026" bucket used by URLs.
     */
    public static function bucketFromMonthKey(string $monthKey): ?string
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', trim($monthKey), $m) !== 1) {
            return null;
        }
        $month = (int)$m[2];
        $names = array(1 => 'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec');
        if (!isset($names[$month])) {
            return null;
        }
        return $names[$month] . $m[1];
    }

    /**
     * Convert a legacy "nov/19/2026" URL date to "2026-11-19".
     */
    public static function dateKeyFromLegacy(string $legacyDate): ?string
    {
        return self::dateKey($legacyDate);
    }
}
