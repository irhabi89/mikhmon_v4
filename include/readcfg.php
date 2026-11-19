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

// --- Session / admin config: SQLite is authoritative; include/config.php ($data) is the fallback. ---
if (!function_exists('mikhmon_session_get')) {
    include_once(__DIR__ . '/../lib/mikhmon_db.php');
    include_once(__DIR__ . '/../lib/sessions_store.php');
}
$session = isset($session) ? $session : '';
$store_session = mikhmon_session_get((string)$session);
if (is_array($store_session)) {
    $iphost = isset($store_session['ip_host']) ? $store_session['ip_host'] : '';
    $userhost = isset($store_session['user_host']) ? $store_session['user_host'] : '';
    $passwdhost = isset($store_session['pass_host']) ? $store_session['pass_host'] : '';
    $hotspotname = isset($store_session['hotspot_name']) ? $store_session['hotspot_name'] : '';
    $dnsname = isset($store_session['dns_name']) ? $store_session['dns_name'] : '';
    $currency = isset($store_session['currency']) ? $store_session['currency'] : '';
    $areload = isset($store_session['areload']) ? $store_session['areload'] : '';
    if ($areload === '' || !is_numeric($areload)) {
        $areload = '10';
    }
    $iface = isset($store_session['iface']) ? $store_session['iface'] : '';
    $infolp = isset($store_session['infolp']) ? $store_session['infolp'] : '';
    $idleto = isset($store_session['idleto']) ? $store_session['idleto'] : '';
    $livereport = isset($store_session['live_report']) ? $store_session['live_report'] : '';
} elseif (isset($data[$session]) && is_array($data[$session])) {
    // Legacy fallback while include/config.php still exists.
    $row = mikhmon_row_from_data((string)$session, $data[$session]);
    $iphost = $row['ip_host'];
    $userhost = $row['user_host'];
    $passwdhost = $row['pass_host'];
    $hotspotname = $row['hotspot_name'];
    $dnsname = $row['dns_name'];
    $currency = $row['currency'];
    $areload = $row['areload'];
    $iface = $row['iface'];
    $infolp = $row['infolp'];
    $idleto = $row['idleto'];
    $livereport = $row['live_report'];
} else {
    $iphost = $userhost = $passwdhost = $hotspotname = $dnsname = $currency = '';
    $areload = '10';
    $iface = $infolp = $idleto = $livereport = '';
}

$store_admin = mikhmon_admin_get();
if (!empty($store_admin['user'])) {
    $useradm = $store_admin['user'];
    $passadm = $store_admin['pass'];
} elseif (isset($data['mikhmon']) && is_array($data['mikhmon'])) {
    $useradm = isset($data['mikhmon'][1]) ? $get_cfg_val($data['mikhmon'][1], '<|<') : '';
    $passadm = isset($data['mikhmon'][2]) ? $get_cfg_val($data['mikhmon'][2], '>|>') : '';
}

$cekindo['indo'] = array(
    'RP', 'Rp', 'rp', 'IDR', 'idr', 'RP.', 'Rp.', 'rp.', 'IDR.', 'idr.',
);


