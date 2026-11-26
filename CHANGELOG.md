# Changelog — MIKHMON v4 GHAIB-NETWORK Edition

All notable changes to this edition. This project is based on Mikhmon v3 by
Laksamadi Guko and is maintained by **PT GHAIB-NETWORK**.
Developer: **quiqxiq**.

## [4.0.0] — 2026-11-26

### Base edition (`feat/report-sqlite-ui`)

- SQLite-backed sales report with pagination, search, date/month/server
  filters, filtered totals, CSV export, print view, and sync status.
- Streamed RouterOS sales scan and exact reconciliation, including large
  archives.
- Hotspot server tracking in sales records (`|#MMSRV1#|<server>`) with
  per-server filters and dashboard cards.
- Server-aware on-login generator and safe profile upgrade/rollback.
- Responsive modern UI, consistent button sizing, accessible modals, and
  mobile layout.
- Performance work: short-lived result cache, async dashboard server cards,
  removal of duplicate count-only API calls, cached profile/scheduler reads,
  and `.proplist` payload reduction.

### License and attribution

- Original project: Mikhmon by Laksamadi Guko (GPLv2).
- Modified and maintained by PT GHAIB-NETWORK.
- Developer: quiqxiq.
- See `NOTICE.md` for attribution.
