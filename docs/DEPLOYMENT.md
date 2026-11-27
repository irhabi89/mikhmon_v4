# Deployment — cPanel / Shared Hosting

**MIKHMON v4 — GHAIB-NETWORK Edition**  
Maintained by PT GHAIB-NETWORK · Developer: quiqxiq.

This guide deploys the application with its runtime data **inside the project
directory** (`data/mikhmon.db`), which is the default configuration. An
optional external-data layout is described at the end.

## 1. Requirements

- PHP 8.0–8.3 recommended (PHP 7.4 compatible).
- Extensions: `pdo`, `pdo_sqlite`, `json`, `mbstring`.
- Composer (or upload `vendor/` from a trusted build).
- Apache/LiteSpeed with `mod_rewrite` and `.htaccess` support, or Nginx with
  equivalent deny rules.
- cPanel Terminal or SSH for Composer/migrations/cron.

Check in cPanel Terminal:

```bash
php -v
php -m | grep -Ei 'pdo|sqlite|json|mbstring'
which php
git --version
composer --version
```

Enable `pdo_sqlite`/`sqlite3` in **MultiPHP Manager → Extensions** if missing.

## 2. Quick setup (recommended)

After cloning/uploading the project, run from the project root:

```bash
bash setup.sh
```

The script checks PHP/extensions, creates `data/` subdirectories, creates
`.env` from `.env.example` if missing, installs Composer dependencies, runs
the database migration with backup, and prints the next steps.

Options:

```bash
# Import an existing clean SQLite backup
bash setup.sh --import-db /home/USER/mikhmon-deploy.db

# Also run an initial router sync
bash setup.sh --sync livebarokah

# Use a specific PHP CLI
PHP_BIN=/usr/local/bin/php bash setup.sh
```

It does **not** overwrite an existing `.env`, and it backs up an existing
database before importing a replacement.

### cPanel Terminal shortcut

If cPanel provides Terminal, the complete first run is:

```bash
cd ~/mikhmon
bash setup.sh
nano .env
# set MIKHMON_PUBLIC_URL only if using webhooks
php bin/mikhmon db:migrate --backup
```

Then set the subdomain document root to `~/mikhmon`, open the site, and verify
`/data/mikhmon.db` returns 403/404.

## 3. Upload the code

Pick one branch:

| Branch | Use |
|---|---|
| `feat/report-sqlite-ui` | Base: SQLite report, server tracking, responsive UI |
| `99barokah` | Base + PPP/auto-isolir/dashboard PPP |
| `parto` | Base + voucher small/new Print New |

```bash
cd ~
git clone -b parto https://github.com/quiqxiq/mikhmon_v4.git mikhmon
cd mikhmon
```

Without Git: upload the branch ZIP via File Manager and extract to
`~/mikhmon`.

Do **not** upload the local `parto/` reference folder, `data/mikhmon.db`,
`data/backups/`, `data/logs/`, or `.env`.

## 4. Document root

Recommended: a subdomain such as `mikhmon.example.com` with document root:

```text
/home/USER/mikhmon
```

If cPanel only allows `public_html`, install under:

```text
/home/USER/public_html/mikhmon
```

and open `https://example.com/mikhmon/`.

The root `.htaccess` protects `data/`, `src/`, `vendor/`, `tests/`,
`migrations/`, `bin/`, and `resources/` from HTTP access.

## 5. Install dependencies

```bash
cd ~/mikhmon
composer install --no-dev --optimize-autoloader
```

If Composer is unavailable:

```bash
mkdir -p ~/bin
curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
php /tmp/composer-setup.php --install-dir=$HOME/bin --filename=composer
~/bin/composer install --no-dev --optimize-autoloader
```

## 6. Configure `.env` (data inside project)

```bash
cd ~/mikhmon
cp .env.example .env
nano .env
```

```ini
MIKHMON_DB_PATH=data/mikhmon.db
MIKHMON_TIMEZONE=Asia/Jakarta
MIKHMON_SYNC_WORKER=cli
MIKHMON_PHP_CLI=/usr/local/bin/php
MIKHMON_LOG_LEVEL=info
# Optional: public URL for RouterOS on-login webhooks.
# Leave empty to keep webhook disabled; script fallback still records sales.
MIKHMON_PUBLIC_URL=https://mikhmon.example.com
```

If `MIKHMON_PUBLIC_URL` is filled, it must be reachable from RouterOS.
Do not use `localhost` or a LAN-only address. If it is empty, no webhook is
embedded in on-login; the `/system/script` fallback remains active.
Save the hotspot profile after changing this value so the on-login snippet is
regenerated.

`data/mikhmon.db` is resolved relative to the project root, so it also works
from cron or CLI.

Create the runtime folders and permissions:

```bash
mkdir -p data/backups data/cache data/locks data/logs
chmod 750 data
chmod -R u+rwX data
chmod 600 .env
```

## 7. Import existing sales data (optional)

Create a clean backup locally; do not copy an open `-wal`/`-shm` pair:

```bash
sqlite3 data/mikhmon.db ".backup '/tmp/mikhmon-deploy.db'"
gzip -9 /tmp/mikhmon-deploy.db
```

Upload `mikhmon-deploy.db.gz` via File Manager to `~/mikhmon/data/`, then:

```bash
cd ~/mikhmon/data
gunzip mikhmon-deploy.db.gz
mv mikhmon-deploy.db mikhmon.db
chmod 640 mikhmon.db
```

For a fresh install, skip this section.

## 8. Migrate and verify

```bash
cd ~/mikhmon
php bin/mikhmon db:migrate --backup
php bin/mikhmon db:maintenance --check
php bin/mikhmon sync:status --session=livebarokah
```

If the database is new:

```bash
php bin/mikhmon sync:run --session=livebarokah
```

Backups are written to `data/backups/`.

## 9. Web security check

Open:

```text
https://your-domain/data/mikhmon.db
https://your-domain/.env
```

Both must return **403 Forbidden** or **404**.

If they download the file, the web server ignores `.htaccess` (for example
plain Nginx). Move the database outside the document root immediately:

```bash
mkdir -p /home/USER/mikhmon-data
# edit .env:
# MIKHMON_DB_PATH=/home/USER/mikhmon-data/mikhmon.db
```

Add the Nginx equivalent:

```nginx
location ^~ /data/ { deny all; }
location ^~ /migrations/ { deny all; }
location ~ /\.(?!well-known) { deny all; }
```

## 10. Cron sync (optional)

The application does **not** start automatic sync by default. Add a cron only
if you intentionally want scheduled sync; otherwise skip this section and use
the manual Sync button.

Use cPanel → Cron Jobs, or `crontab -e`:

```cron
0 * * * * /usr/local/bin/php /home/USER/mikhmon/bin/mikhmon sync:run --session=livebarokah >> /home/USER/mikhmon/data/logs/sync.log 2>&1
```

Use the same PHP binary as `MIKHMON_PHP_CLI`. If `exec` is disabled, the UI
falls back to a web worker; cron is still more reliable for large archives.

## 11. First login and hardening

1. Open the domain/subdomain.
2. Login with `mikhmon` / `1234`.
3. Change the admin password in **Pengaturan**.
4. Add the router session and test Dashboard, Report, Users, and About.
5. Confirm About shows `MIKHMON v4.0.0 — GHAIB-NETWORK Edition`.
6. Enable HTTPS.

## 12. Update procedure

```bash
cd ~/mikhmon
git pull
composer install --no-dev --optimize-autoloader
php bin/mikhmon db:migrate --backup
```

Never overwrite `.env` or `data/mikhmon.db` during an update.

## 13. Optional: data outside the document root

```bash
mkdir -p /home/USER/mikhmon-data/{backups,cache,logs,locks}
# .env:
# MIKHMON_DB_PATH=/home/USER/mikhmon-data/mikhmon.db
```

Keep `data/` for application-created backups/logs/cache when needed, and make
sure both locations are writable by the cPanel user.

## Troubleshooting

- **500 / blank page**: check `error_log`, PHP version, and `pdo_sqlite`.
- **Database is read-only**: fix ownership/permissions of `data/`.
- **Data URL downloads**: `.htaccess` is ignored; move data outside webroot.
- **Composer platform error**: select a supported PHP version or run
  `composer install --ignore-platform-req=php` only after checking extensions.
- **Sync never finishes**: use cron, check `data/logs/sync-*.log`, and ensure
  RouterOS API port is reachable.
