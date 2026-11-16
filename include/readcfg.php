<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// hide all error
error_reporting(0);
if (isset($_SERVER["REQUEST_URI"]) && substr($_SERVER["REQUEST_URI"], -11) == "readcfg.php") {
    header("Location:./");
    exit;
}
// read config
include_once(__DIR__ . '/timezone.php');
if (!empty($_SESSION['timezone'])) {
    date_default_timezone_set(mikhmon_valid_timezone($_SESSION['timezone']));
}

$get_cfg_val = function ($str, $delim) {
    if (!is_string($str) || $delim === '') return '';
    $p = explode($delim, $str, 2);
    return isset($p[1]) ? $p[1] : '';
};

// Drop the PHP opcache/stat cache for a freshly written file so the next
// request immediately sees the new configuration (avoid stale include data).
if (!function_exists('mikhmon_cache_invalidate')) {
    function mikhmon_cache_invalidate($path)
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
        @clearstatcache(true, $path);
    }
}

if (isset($data[$session]) && is_array($data[$session])) {
    $iphost = isset($data[$session][1]) ? $get_cfg_val($data[$session][1], '!') : '';
    $userhost = isset($data[$session][2]) ? $get_cfg_val($data[$session][2], '@|@') : '';
    $passwdhost = isset($data[$session][3]) ? $get_cfg_val($data[$session][3], '#|#') : '';
    $hotspotname = isset($data[$session][4]) ? $get_cfg_val($data[$session][4], '%') : '';
    $dnsname = isset($data[$session][5]) ? $get_cfg_val($data[$session][5], '^') : '';
    $currency = isset($data[$session][6]) ? $get_cfg_val($data[$session][6], '&') : '';
    $areload = isset($data[$session][7]) ? $get_cfg_val($data[$session][7], '*') : '';
    if ($areload === '' || !is_numeric($areload)) {
        $areload = '10';
    }
    $iface = isset($data[$session][8]) ? $get_cfg_val($data[$session][8], '(') : '';
    $infolp = isset($data[$session][9]) ? $get_cfg_val($data[$session][9], ')') : '';
    $idleto = isset($data[$session][10]) ? $get_cfg_val($data[$session][10], '=') : '';
    $sesname = isset($data[$session][10]) ? $get_cfg_val($data[$session][10], '+') : '';
    $livereport = isset($data[$session][11]) ? $get_cfg_val($data[$session][11], '@!@') : '';
}
if (isset($data['mikhmon']) && is_array($data['mikhmon'])) {
    $useradm = isset($data['mikhmon'][1]) ? $get_cfg_val($data['mikhmon'][1], '<|<') : '';
    $passadm = isset($data['mikhmon'][2]) ? $get_cfg_val($data['mikhmon'][2], '>|>') : '';
}

$cekindo['indo'] = array(
    'RP', 'Rp', 'rp', 'IDR', 'idr', 'RP.', 'Rp.', 'rp.', 'IDR.', 'idr.',
);


