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
session_start();
// hide all error
error_reporting(0);

function mikhmon_ping_escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function mikhmon_ping_card($title, $body, $ok = false)
{
    return '<div id="pingX" class="col-12">'
        . '<div class="card">'
        . '<div class="card-header">'
        . '<h3 class="card-title">' . mikhmon_ping_escape($title) . '</h3>'
        . '</div>'
        . '<div class="card-body">' . $body
        . '<br><span class="pointer btn bg-grey" onclick="closeX()"><i class="fa fa-close text-red "></i> Close</span>'
        . '</div>'
        . '</div>'
        . '</div>';
}

if (!isset($_SESSION["mikhmon"])) {
    http_response_code(401);
    echo mikhmon_ping_card('Ping Test', '<b class="text-red">Sesi login berakhir.</b><br>Silakan login ulang lalu coba lagi.');
    exit;
}

$session = isset($_REQUEST['session']) ? (string)$_REQUEST['session'] : '';
$ping = isset($_REQUEST['ping']) ? $_REQUEST['ping'] : '';
$input = isset($_REQUEST['ip']) ? trim((string)$_REQUEST['ip']) : '';

if (empty($ping)) {
    exit;
}

// The settings form sends the IP currently typed in the form, so Ping works
// for saved routers as well as brand-new/unsaved routers. Fall back to the
// saved configuration when the caller only passes a session.
if ($input === '' && $session !== '') {
    include_once('../include/config.php');
    include_once('../include/readcfg.php');
    $input = isset($iphost) ? trim((string)$iphost) : '';
}

if ($input === '') {
    echo mikhmon_ping_card(
        'Ping Test',
        '<b class="text-warning">IP MikroTik belum diisi.</b><br>Isi kolom IP MikroTik terlebih dahulu, lalu klik Ping.'
    );
    exit;
}

// Accept "host", "host:port", "[ipv6]" and "[ipv6]:port". Strip a scheme or
// path in case the user pasted a URL into the IP field.
$input = preg_replace('/\s+/', '', $input);
$input = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $input);
$input = preg_replace('#/.*$#', '', $input);

$host = $input;
$port = 8728;

if (preg_match('/^\[([^\]]+)\](?::([0-9]+))?$/', $input, $m)) {
    $host = $m[1];
    if (isset($m[2]) && $m[2] !== '') {
        $port = (int)$m[2];
    }
} elseif (substr_count($input, ':') === 1) {
    list($h, $p) = explode(':', $input, 2);
    if ($h !== '' && $p !== '' && ctype_digit($p)) {
        $host = $h;
        $port = (int)$p;
    }
}

if ($host === '' || $port < 1 || $port > 65535) {
    echo mikhmon_ping_card(
        'Ping Test [' . mikhmon_ping_escape($input) . ']',
        '<b class="text-warning">Format alamat tidak valid.</b><br>Gunakan format <b>IP</b> atau <b>IP:port</b>, contoh <b>192.168.88.1:8728</b>.'
    );
    exit;
}

$start = microtime(true);
$fsock = @fsockopen($host, $port, $errno, $errstr, 5);
$elapsed = (int)round((microtime(true) - $start) * 1000);

$host_label = mikhmon_ping_escape($host);
$port_label = mikhmon_ping_escape($port);

if (!$fsock) {
    $detail = trim((string)$errstr);
    if ($detail === '') {
        $detail = 'Tidak dapat membuka koneksi TCP.';
    }
    echo mikhmon_ping_card(
        'Ping Test [' . $host_label . ':' . $port_label . ']',
        'Host : ' . $host_label . '&nbsp; Port : ' . $port_label . '<br>'
        . '<b class="text-warning">Ping Timeout / Tidak Terhubung</b><br>'
        . 'Penyebab : ' . mikhmon_ping_escape($detail) . '<br>'
        . 'Pastikan IP/port benar, service API MikroTik aktif, dan jaringan/VPN terhubung.'
    );
    exit;
}

fclose($fsock);
echo mikhmon_ping_card(
    'Ping Test [' . $host_label . ':' . $port_label . ']',
    'Host : ' . $host_label . '&nbsp; Port : ' . $port_label . '<br>'
    . '<b class="text-green">Ping OK</b> (TCP terhubung dalam ' . $elapsed . ' ms)'
);
