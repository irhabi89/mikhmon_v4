# Branch integration

`feat/report-sqlite-ui` is the base. Feature branches are kept separate and
receive the base via a merge commit (never force-push).

## 99barokah (PPP + auto isolir + PPP dashboard cards)

Local integration branch: `integration/99barokah`.

```bash
git merge feat/report-sqlite-ui
```

Resolved rules:

- Keep `ppp/` and `process/psecret.php` / `removepprofile.php`.
- Keep the PPP menu and `lang/*` keys.
- Keep `dashboard/home.php` PPP cards; use
  `resources/views/components/server-cards.php` for per-server cards.
- `dashboard/home1.php` is an unused duplicate and should be removed.
- Do not overwrite the SQLite session DB with `include/config.php` arrays.

## parto (per-server dashboard)

Local integration branch: `integration/parto`.

- The old manual username -> hotspot-user server join is removed.
- Use `sales_records.server_name` and `SalesRepository::serverStats()`.
- Active/user counts still come from one `/ip/hotspot/active/print` and
  `/ip/hotspot/user/print` with `.proplist=server`.
- Legacy rows (`server_name=''`) appear as "Tanpa server".

## Verification after each merge

```bash
composer install
composer test
php -l (all changed files)
```

Smoke test: dashboard, report selling, PPP pages (99barokah), server filter.
