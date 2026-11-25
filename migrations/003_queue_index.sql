-- Queue scans are by (session, state); the name order was forcing a sort.
CREATE INDEX IF NOT EXISTS srr_session_state_name
  ON sales_router_records(session, state, name);
