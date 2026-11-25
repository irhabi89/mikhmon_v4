<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
/*
 * Mikhmon v3 Phase 2: import config ($data from include/config.php) into SQLite.
 *
 * Usage:
 *   php migrations/import_config.php            import admin + sessions
 *   php migrations/import_config.php --verify   compare DB rows with config.php
 */
require_once dirname(__DIR__) . '/lib/mikhmon_db.php';
require_once dirname(__DIR__) . '/lib/sessions_store.php';
require_once dirname(__DIR__) . '/lib/routeros_api.class.php';

// config.php sets $data in this script's scope (the URL guard in the file
// is inert when included via CLI).
include_once dirname(__DIR__) . '/include/config.php';

$data = isset($data) ? $data : array();

function mikhmon_import_config($data)
{
    mikhmon_db_init();
    $names = array();
    foreach ($data as $name => $val) {
        if ($name === 'mikhmon') {
            $row = mikhmon_row_from_data('mikhmon', $val);
            mikhmon_admin_set($row['user_host'], $row['pass_host']);
            continue;
        }
        if (!is_array($val)) {
            continue;
        }
        $row = mikhmon_row_from_data((string)$name, $val);
        if (mikhmon_session_upsert($row)) {
            $names[] = $row['name'];
        }
    }
    return $names;
}

function mikhmon_verify_config($data)
{
    mikhmon_db_init();
    $problems = array();
    $fields = array('ip_host', 'user_host', 'pass_host', 'hotspot_name', 'dns_name',
        'currency', 'areload', 'iface', 'infolp', 'idleto', 'live_report');
    foreach ($data as $name => $val) {
        if ($name === 'mikhmon') {
            $expect = mikhmon_row_from_data('mikhmon', $val);
            $got = mikhmon_admin_get();
            foreach (array('user_host' => 'user', 'pass_host' => 'pass') as $src => $dst) {
                $a = (string)(isset($expect[$src]) ? $expect[$src] : '');
                $b = (string)(isset($got[$dst]) ? $got[$dst] : '');
                if ($a !== $b) {
                    $problems[] = "admin.$src: config='$a' db='$b'";
                }
            }
        } elseif (is_array($val)) {
            $expect = mikhmon_row_from_data((string)$name, $val);
            $got = mikhmon_session_get((string)$name);
            if ($got === null) {
                $problems[] = "$name: session missing in DB";
                continue;
            }
            foreach ($fields as $f) {
                $a = (string)(isset($expect[$f]) ? $expect[$f] : '');
                $b = (string)(isset($got[$f]) ? $got[$f] : '');
                if ($a !== $b) {
                    $problems[] = "$name.$f: config='$a' db='$b'";
                }
            }
        }
    }
    return $problems;
}

$argv = $_SERVER['argv'] ?? array();
if (in_array('--verify', $argv)) {
    $problems = mikhmon_verify_config($data);
    if ($problems === array()) {
        echo "VERIFY OK: DB matches include/config.php\n";
        exit(0);
    }
    echo "VERIFY FAIL:\n";
    foreach ($problems as $p) {
        echo "  - $p\n";
    }
    exit(1);
}

$names = mikhmon_import_config($data);
echo "import_config: sessions=" . (count($names) ? implode(',', $names) : '(none)') . "\n";
