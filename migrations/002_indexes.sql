-- Cover the report's default sort (date, time, id) so deep pages do not sort.
CREATE INDEX IF NOT EXISTS sales_session_date_time
  ON sales_records(session, date_key DESC, time_key DESC, id DESC);
