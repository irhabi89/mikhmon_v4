<?php
/**
 * Resolve the RouterOS clock into a PHP timezone identifier.
 *
 * RouterOS may report time-zone-name=manual (or another name PHP does not
 * know) while still exposing a usable gmt-offset. Prefer the named zone so
 * DST rules are kept, otherwise use a fixed-offset Etc/GMT zone.
 */
function mikhmon_timezone_from_clock($clock)
{
    $name = isset($clock['time-zone-name']) ? $clock['time-zone-name'] : '';
    if ($name !== '' && in_array($name, timezone_identifiers_list(), true)) {
        return $name;
    }

    $offset = isset($clock['gmt-offset']) ? $clock['gmt-offset'] : '';
    if (preg_match('/^([+-])(\d{2}):(\d{2})$/', $offset, $m)) {
        $seconds = ((int) $m[2] * 3600) + ((int) $m[3] * 60);
        if ($m[1] === '-') {
            $seconds = -$seconds;
        }

        if ($seconds % 3600 === 0) {
            $hours = intdiv($seconds, 3600);
            if ($hours === 0) {
                return 'Etc/GMT';
            }
            return 'Etc/GMT' . ($hours > 0 ? '-' . $hours : '+' . abs($hours));
        }

        $tz = timezone_name_from_abbr('', $seconds, 0);
        if ($tz !== false) {
            return $tz;
        }
    }

    return 'UTC';
}

/**
 * Validate a timezone stored in the session before passing it to PHP.
 */
function mikhmon_valid_timezone($timezone)
{
    if (is_string($timezone) && $timezone !== '' && in_array($timezone, timezone_identifiers_list(), true)) {
        return $timezone;
    }
    return 'UTC';
}
