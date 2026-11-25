<?php
/*
 * Mikhmon v3 SQLite storage layer (Phase 1).
 *
 * All data (admin account, routers/"sessions", sales records) lives in
 * SQLite at data/mikhmon.db. The legacy PHP-file storage in
 * include/config.php is being phased out (a backup is kept in
 * data/config.php.bak; see MIGRATION_PLAN in .opencode/plan).
 *
 * Functions catch PDO exceptions and return null/[]/false/0 when the DB is
 * unavailable so pages degrade gracefully during the transition.
 */

function mikhmon_db()
{
    static $db = null;
    if ($db === null) {
        $path = dirname(__DIR__) . '/data/mikhmon.db';
        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0755, true);
        }
        try {
            $db = new PDO('sqlite:' . $path);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->exec('PRAGMA journal_mode=WAL');
            $db->exec('PRAGMA busy_timeout=5000');
            // Ensure the schema (including new tables added by upgrades) exists.
            mikhmon_db_init();
        } catch (PDOException $e) {
            $db = false;
        }
    }
    return $db;
}

function mikhmon_db_init()
{
    $db = mikhmon_db();
    if ($db === false) {
        return false;
    }
    try {
        // New sales tables are owned by src/Storage/Migrator.php. The legacy
        // init must not recreate the old sales_records/sales_import_queue after
        // the v2 migration has parked them.
        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS mikhmon_config (
  id INTEGER PRIMARY KEY CHECK (id = 1),
  admin_user TEXT NOT NULL DEFAULT '',
  admin_pass TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS mikhmon_sessions (
  name TEXT PRIMARY KEY,
  ip_host TEXT NOT NULL DEFAULT '',
  port_host INTEGER NOT NULL DEFAULT 8728,
  user_host TEXT NOT NULL DEFAULT '',
  pass_host TEXT NOT NULL DEFAULT '',
  hotspot_name TEXT NOT NULL DEFAULT '',
  dns_name TEXT NOT NULL DEFAULT '',
  currency TEXT NOT NULL DEFAULT '',
  areload TEXT NOT NULL DEFAULT '',
  iface TEXT NOT NULL DEFAULT '',
  infolp TEXT NOT NULL DEFAULT '',
  idleto TEXT NOT NULL DEFAULT '',
  live_report TEXT NOT NULL DEFAULT '',
  raw TEXT
);
SQL);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function mikhmon_db_ok()
{
    return (mikhmon_db() !== false);
}
