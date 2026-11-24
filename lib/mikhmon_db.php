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
        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS mikhmon_config (
  id INTEGER PRIMARY KEY CHECK (id = 1),
  admin_user TEXT NOT NULL DEFAULT '',
  admin_pass TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS mikhmon_sessions (
  name TEXT PRIMARY KEY,
  ip_host TEXT NOT NULL DEFAULT '',
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
CREATE TABLE IF NOT EXISTS sales_records (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  session TEXT NOT NULL DEFAULT '',
  ros_script_id TEXT NOT NULL DEFAULT '',
  name TEXT NOT NULL DEFAULT '',
  source TEXT NOT NULL DEFAULT '',
  owner TEXT NOT NULL DEFAULT '',
  comment TEXT NOT NULL DEFAULT '',
  c_date TEXT NOT NULL DEFAULT '',
  c_time TEXT NOT NULL DEFAULT '',
  c_username TEXT NOT NULL DEFAULT '',
  c_price TEXT NOT NULL DEFAULT '',
  c_address TEXT NOT NULL DEFAULT '',
  c_mac TEXT NOT NULL DEFAULT '',
  c_validity TEXT NOT NULL DEFAULT '',
  c_profile TEXT NOT NULL DEFAULT '',
  c_comment TEXT NOT NULL DEFAULT '',
  imported_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now'))
);
CREATE UNIQUE INDEX IF NOT EXISTS sales_records_script
  ON sales_records(session, ros_script_id) WHERE ros_script_id != '';
CREATE UNIQUE INDEX IF NOT EXISTS sales_records_session_name ON sales_records(session, name);
CREATE INDEX IF NOT EXISTS sales_records_session_source ON sales_records(session, source);
CREATE INDEX IF NOT EXISTS sales_records_session_owner ON sales_records(session, owner);
CREATE INDEX IF NOT EXISTS sales_records_session_date ON sales_records(session, c_date);
CREATE TABLE IF NOT EXISTS sales_import_queue (
  session TEXT NOT NULL DEFAULT '',
  name TEXT NOT NULL DEFAULT '',
  done INTEGER NOT NULL DEFAULT 0,
  PRIMARY KEY (session, name)
);
CREATE INDEX IF NOT EXISTS sales_import_queue_done ON sales_import_queue(session, done);
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
