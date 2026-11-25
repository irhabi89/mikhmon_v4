-- Server-aware sales reporting. Columns are added by Migrator with guards.
CREATE INDEX IF NOT EXISTS sales_session_server_date
  ON sales_records(session, server_name, date_key DESC, id DESC);
CREATE INDEX IF NOT EXISTS srr_session_server
  ON sales_router_records(session, server_name);
