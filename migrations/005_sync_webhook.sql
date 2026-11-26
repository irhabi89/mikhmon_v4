-- Optional manual-import cleanup and per-session webhook settings.
-- Columns are added by Migrator with columnExists guards so this file is
-- index-safe and can be applied to fresh or existing databases.
CREATE INDEX IF NOT EXISTS ssr_session_delete_flag
  ON sales_sync_runs(session, delete_after_import);
