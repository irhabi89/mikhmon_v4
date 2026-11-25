-- Mikhmon sales schema v1 (baseline).
-- Old sales tables are renamed to *_legacy_v1 by Migrator before this runs.
-- sessions/config are kept for the existing UI.

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

CREATE TABLE IF NOT EXISTS sales_records (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  session TEXT NOT NULL,
  name TEXT NOT NULL,
  ros_script_id TEXT NOT NULL DEFAULT '',
  source_date TEXT NOT NULL DEFAULT '',
  date_key TEXT NOT NULL,
  month_key TEXT NOT NULL,
  time_key TEXT NOT NULL DEFAULT '',
  username TEXT NOT NULL DEFAULT '',
  profile TEXT NOT NULL DEFAULT '',
  sale_comment TEXT NOT NULL DEFAULT '',
  address TEXT NOT NULL DEFAULT '',
  mac TEXT NOT NULL DEFAULT '',
  validity TEXT NOT NULL DEFAULT '',
  price REAL NOT NULL DEFAULT 0,
  price_raw TEXT NOT NULL DEFAULT '',
  script_comment TEXT NOT NULL DEFAULT '',
  imported_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S','now')),
  UNIQUE(session, name)
);

CREATE INDEX IF NOT EXISTS sales_session_date
  ON sales_records(session, date_key DESC, id DESC);
CREATE INDEX IF NOT EXISTS sales_session_month
  ON sales_records(session, month_key, id DESC);
CREATE INDEX IF NOT EXISTS sales_session_username
  ON sales_records(session, username COLLATE NOCASE);
CREATE INDEX IF NOT EXISTS sales_session_price
  ON sales_records(session, price);

CREATE TABLE IF NOT EXISTS sales_router_records (
  session TEXT NOT NULL,
  name TEXT NOT NULL,
  ros_script_id TEXT NOT NULL DEFAULT '',
  script_comment TEXT NOT NULL DEFAULT '',
  state TEXT NOT NULL DEFAULT 'pending',
  run_id INTEGER NOT NULL DEFAULT 0,
  first_seen_at TEXT NOT NULL DEFAULT '',
  last_seen_at TEXT NOT NULL DEFAULT '',
  imported_at TEXT NOT NULL DEFAULT '',
  dup_count INTEGER NOT NULL DEFAULT 1,
  error TEXT NOT NULL DEFAULT '',
  PRIMARY KEY (session, name)
);
CREATE INDEX IF NOT EXISTS srr_session_state ON sales_router_records(session, state);
CREATE INDEX IF NOT EXISTS srr_session_run   ON sales_router_records(session, run_id);

CREATE TABLE IF NOT EXISTS sales_sync_runs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  session TEXT NOT NULL,
  mode TEXT NOT NULL DEFAULT 'full',
  month_key TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL DEFAULT 'queued',
  phase TEXT NOT NULL DEFAULT '',
  worker TEXT NOT NULL DEFAULT '',
  expected_scripts INTEGER NOT NULL DEFAULT -1,
  scanned_scripts INTEGER NOT NULL DEFAULT 0,
  router_records INTEGER NOT NULL DEFAULT 0,
  imported INTEGER NOT NULL DEFAULT 0,
  missing INTEGER NOT NULL DEFAULT 0,
  extra INTEGER NOT NULL DEFAULT 0,
  duplicates INTEGER NOT NULL DEFAULT 0,
  invalid INTEGER NOT NULL DEFAULT 0,
  complete INTEGER NOT NULL DEFAULT 0,
  cancel_requested INTEGER NOT NULL DEFAULT 0,
  error TEXT NOT NULL DEFAULT '',
  started_at TEXT NOT NULL DEFAULT '',
  heartbeat_at TEXT NOT NULL DEFAULT '',
  finished_at TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS ssr_session_status ON sales_sync_runs(session, status, id DESC);
