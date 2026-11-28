# Branch strategy and attribution

**MIKHMON v4 — GHAIB.NET Edition**  
Maintained by **GHAIB.NET** · Developer: **quiqxiq**  
Original Mikhmon by Laksamadi Guko (GPLv2).

## Active branches

| Branch | Purpose | Notes |
|---|---|---|
| `feat/report-sqlite-ui` | Base edition | SQLite sales report/sync, server tracking, responsive UI, performance |
| `99barokah` | PPP edition | Base + PPP module, auto-isolir, PPP dashboard cards |
| `parto` | Voucher edition | Base + server-aware small/new voucher template and Print New button |

No temporary `integration/*` branches are kept. Changes are merged from the
base branch into the two feature branches with normal merge commits; force-push
is not used.

## Merge rules

```bash
git checkout 99barokah
git merge feat/report-sqlite-ui
# resolve dashboard/menu/lang conflicts
composer test
```

For `parto`, preserve the voucher changes (`default-new.php`,
`template-new.php`, Print New button) and per-server dashboard.

## Verification

```bash
composer install
composer test
php -l (all changed files)
```

Smoke test: dashboard, report selling, server filter, PPP pages (99barokah),
voucher Print New (parto).

## Attribution

See [`NOTICE.md`](../NOTICE.md) and [`CHANGELOG.md`](../CHANGELOG.md).
