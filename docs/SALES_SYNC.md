# Sales sync (SQLite ↔ MikroTik)

## One-time upgrade

```bash
composer install --no-dev --optimize-autoloader
bin/mikhmon db:migrate --backup
bin/mikhmon db:maintenance --check
bin/mikhmon sync:run --session=<router>
```

The baseline migration parks the old sales tables as
`sales_records_legacy_v1` / `sales_import_queue_legacy_v1` and creates:

- `sales_records` — report table (normalised date/month/price, pagination indexes).
- `sales_router_records` — complete snapshot of `/system/script` sales records;
  `state` is the import queue (`pending|imported|invalid|deleted`).
- `sales_sync_runs` — progress, heartbeat, reconciliation counts.

The router scan is the source of truth. A full scan must complete before the UI
shows `in_sync=true`.

## Commands

```bash
bin/mikhmon sync:run --session=<router>            # full scan + import + verify
bin/mikhmon sync:run --session=<router> --month=nov2026
bin/mikhmon sync:status --session=<router>         # JSON, no router connection
bin/mikhmon sync:worker --run-id=<id>              # background worker
bin/mikhmon db:maintenance --backup --vacuum --optimize --check
bin/mikhmon db:import-legacy --from=data/mikhmon.db.backup
bin/mikhmon db:clear-sales --session=<router> --yes   # local DB only, router untouched
```

## Reconciliation rules

- `matched` / `missing` / `extra` are set comparisons by script `name`.
- `coverage = matched / router records`.
- `exact = matched / (matched + missing + extra + invalid)`.
- `in_sync` is true only when a full scan is complete and
  `missing=0, extra=0, invalid=0`.
- A DB with the same number of rows but different names is **not** 100%.

## Performance notes

- Router scans use one streamed API response (`!re` per row, bounded memory).
- `sales_records` has `(session, date_key, time_key, id)` and month/username indexes.
- The report page never opens a router connection; only the sync worker does.
- `MIKHMON_SYNC_WORKER=web` can force the chunked month fallback when the host
  disables `exec`.
